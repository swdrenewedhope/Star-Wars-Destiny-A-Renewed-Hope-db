<?php
namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use AppBundle\Entity\Deck;
use AppBundle\Entity\Deckslot;
use AppBundle\Entity\Card;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Deckchange;


class BuilderController extends Controller
{
	public function buildformAction()
	{
		$response = new Response(); $response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
        $affiliations = $this->getDoctrine()->getManager()->getRepository('AppBundle:Affiliation')->findPrimaries();
		$formats = $this->getDoctrine()->getManager()->getRepository('AppBundle:Format')->findAll();
        $setlist = $this->getDoctrine()->getManager()->getRepository('AppBundle:Set')->findAll(); $sets = [];

        foreach($setlist as $set) { $sets[$set->getCode()] = $set->getName(); }

		return $this->render('AppBundle:Builder:initbuild.html.twig', [
				'pagetitle' => $this->get('translator')->trans('decks.form.new'),
                'affiliations' => $affiliations,
                'formats' => $formats,
                'sets' => $sets ], $response);
    }

    public function initbuildAction (Request $request)
    {
        $translator = $this->get('translator');
        $em = $this->getDoctrine()->getManager();
        $affiliation_code = $request->request->get('affiliation');

        if(!$affiliation_code)
        {
        	$this->get('session')->getFlashBag()->set('error', $translator->trans("decks.build.errors.noaffiliation"));
        	return $this->redirect($this->generateUrl('deck_buildform'));
        }

        $affiliation = $em->getRepository('AppBundle:Affiliation')->findByCode($affiliation_code);
        if(!$affiliation)
        {
        	$this->get('session')->getFlashBag()->set('error', $translator->trans("decks.build.errors.noaffiliation"));
        	return $this->redirect($this->generateUrl('deck_buildform'));
        }
		$tags = [ $affiliation_code ];

        $format_code = $request->request->get('format');
        if(!$format_code)
        {
            $this->get('session')->getFlashBag()->set('error', $translator->trans("decks.build.errors.noformat"));
            return $this->redirect($this->generateUrl('deck_buildform'));
        }

        $format = $em->getRepository('AppBundle:Format')->findByCode($format_code);
        if(!$format)
        {
            $this->get('session')->getFlashBag()->set('error', $translator->trans("decks.build.errors.noformat"));
            return $this->redirect($this->generateUrl('deck_buildform'));
        }
        $tags[] = $format->getName();

        $name = $translator->trans("decks.build.newname", array("%affiliation%" => $affiliation->getName()));

        $deck = new Deck();
        $deck->setDescriptionMd("");
        $deck->setAffiliation($affiliation);
        $deck->setFormat($format);
        $deck->setName($name);
        $deck->setProblem('incorrect_size');
        $deck->setTags(join(' ', array_unique($tags)));
        $deck->setUser($this->getUser());

        $em->persist($deck);
        $em->flush();

        return $this->redirect($this->get('router')->generate('deck_edit', ['deck_id' => $deck->getId()]));
    }

    public function fileimportAction (Request $request)
{
    $uploadedFile = $request->files->get('upfile');
    if (!isset($uploadedFile)) { return new Response('No file provided. Returning to /decks..', 400, array('Refresh' => '3; url=/decks')); }
    $filename = $uploadedFile->getPathname(); $parse = $this->parseTextImport(file_get_contents($filename));
	$fileLines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $properties = [
        'name'             => trim($fileLines[0]),
        'affiliation_code' => $parse['affiliation_code'] ?? '',
        'format_code'      => $parse['format_code'] ?? 'INF',
        'content'          => json_encode($parse['content'] ?? []),
        'description'      => $parse['description'] ?? ''
    ]; return $this->forward('AppBundle:Builder:save', $properties);
}

        public function parseTextImport ($text)
    {
        $em = $this->getDoctrine()->getManager();

        $content = []; $affiliation_code = ''; $description = '';
        $lines = preg_split("/\r\n|\n|\r/", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (!$affiliation_code) {
                if (preg_match('/^(hero|villain)$/i', $line, $m)) {
                    $word = strtolower($m[1]);
                    $aff = $em->getRepository('AppBundle:Affiliation')->findOneBy(['code' => $word]);
                    if ($aff) { $affiliation_code = $aff->getCode(); continue;
                }}}

            if (preg_match('/^\s*(\d*)\s*x?\s*(.*?)\s*\(([^)]+)\)/u', $line, $m)) {
                $quantity = ($m[1] === '') ? 1 : (int) $m[1];
                $identifier = trim($m[3]);

                list($setName, $cardNum) = explode(' #', $identifier);
                $cardNum = (int) $cardNum;

                $set = $em->getRepository('AppBundle:Set')->findOneBy(['name' => $setName]);
                if ($set) {
                    $card = $em->getRepository('AppBundle:Card')->findOneBy(['set' => $set,
                        'position' => $cardNum]);

                    if ($card) {
						$content[$card->getCode()]['quantity'] += $quantity;
						if ($content[$card->getCode()]['quantity'] > $card->getDeckLimit()) { $content[$card->getCode()]['quantity'] = $card->getDeckLimit() + 1; }
						if ($card->getHasDie()) { $content[$card->getCode()]['dice'] = $content[$card->getCode()]['quantity']; }
						}
                    }
                }
            }

        return [
            'affiliation_code' => $affiliation_code,
            'content' => $content,
            'description' => $description
        ];
    }

    public function textexportAction ($deck_id) // bad name -> downloadAction
    {
        $em = $this->getDoctrine()->getManager();
        $deck = $em->getRepository('AppBundle:Deck')->find($deck_id);
        
        $is_owner = $this->getUser() && $this->getUser()->getId() == $deck->getUser()->getId();
        if(!$deck->getUser()->getIsShareDecks() && !$is_owner) {
        	return $this->render(
        			'AppBundle:Default:error.html.twig',
        			array('pagetitle' => "Error", 'error' => 'That deck you seek. See it you shall not.'));
			}

        $factionNames = [];
        foreach($this->getDoctrine()->getRepository('AppBundle:Faction')->findAllAndOrderByName() as $faction) {
            $factionNames[$faction->getCode()] = $faction->getName();}

        $content = $this->renderView('AppBundle:Export:plain.txt.twig', [
        	"deck" => $deck->getTextExport(), "factionNames" => $factionNames]);
        $content = str_replace("\n", "\r\n", $content);

		$response = new Response();
		$response->headers->set('Content-Type', 'text/plain');
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition(
		    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
				$this->get('texts')->slugify($deck->getName()) . '.txt'
		)); $response->setContent($content); return $response;
    }

    public function cloneAction ($deck_id)
    {
        $em = $this->getDoctrine()->getManager();
        $deck = $em->getRepository('AppBundle:Deck')->find($deck_id);

        $is_owner = $this->getUser() && $this->getUser()->getId() == $deck->getUser()->getId();
        if(!$deck->getUser()->getIsShareDecks() && !$is_owner) {
        	return $this->render(
        			'AppBundle:Default:error.html.twig',
        			array('pagetitle' => "Error", 'error' => 'That deck you seek. See it you shall not.'));
			}

        $content = $deck->getSlots()->getContent();
        return $this->forward('AppBundle:Builder:save',
            array(
                'name' => $deck->getName().' (clone)',
                'affiliation_code' => $deck->getAffiliation()->getCode(),
                'format_code' => $deck->getFormat()->getCode(),
                'content' => json_encode($content),
                'deck_id' => $deck->getParent() ? $deck->getParent()->getId() : null
            ));
    }

    public function saveAction (Request $request)
    {
        $em = $this->getDoctrine()->getManager(); $user = $this->getUser();
        if (count($user->getDecks()) > $user->getMaxNbDecks())
            return new Response('You have reached the maximum number of decks allowed. Impressive!');
        $id = filter_var($request->get('id'), FILTER_SANITIZE_NUMBER_INT);
        $deck = null; $source_deck = null;

        if($id) {
            $deck = $em->getRepository('AppBundle:Deck')->find($id);
            if (!$deck || $user->getId() != $deck->getUser()->getId()) {
                throw new UnauthorizedHttpException("You don't have access to this deck.");
            }
            $source_deck = $deck;
        }

		$affiliation_code = $request->get('affiliation_code');
		if(!$affiliation_code) { return new Response('Cannot import deck without affiliation'); }

		$affiliation = $em->getRepository('AppBundle:Affiliation')->findOneBy(['code' => $affiliation_code]);
		if(!$affiliation) { return new Response('Cannot import deck with unknown affiliation ' . $affiliation_code); }

        $format_code = $request->get('format_code') ?: 'INF';
		if(!$format_code) { return new Response('Cannot import deck without format'); }

        $format = $em->getRepository('AppBundle:Format')->findOneBy(['code' => $format_code]);
        if(!$format) { return new Response('Cannot import deck with unknown format ' . $format_code); }

        $cancel_edits = (bool) $request->get('cancel_edits');
        if($cancel_edits) { if($deck) $this->get('decks')->revertDeck($deck);
            return $this->redirect($this->generateUrl('decks_list'));}

        $is_copy = (bool) $request->get('copy');
        if($is_copy || !$id) { $deck = new Deck(); }

        $content = (array) json_decode($request->get('content'), true);
        if (! count($content)) {
            return new Response('Cannot import empty deck' . json_encode($content));
        }

        $name = filter_var($request->get('name'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $decklist_id = filter_var($request->get('decklist_id'), FILTER_SANITIZE_NUMBER_INT);
        $description = trim($request->get('description'));
        $tags = filter_var($request->get('tags'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

        $this->get('decks')->saveDeck($this->getUser(), $deck, $decklist_id, $name, $affiliation, $format, $description, $tags, $content, $source_deck ? $source_deck : null);
        $em->flush();
        
        return $this->redirect($this->generateUrl('decks_list'));

    }

    public function deleteAction (Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $deck_id = $request->get('deck_id'); $deck = $em->getRepository('AppBundle:Deck')->find($deck_id);
        
		if (!$deck) return $this->redirect($this->generateUrl('decks_list'));
        if ($this->getUser()->getId() != $deck->getUser()->getId())
            throw new UnauthorizedHttpException("That deck you seek. See it you shall not.");

        foreach ($deck->getChildren() as $decklist) {
            $decklist->setParent(null);
        }
        $em->remove($deck); $em->flush();

        $this->get('session')->getFlashBag()->set('notice', "Deck deleted.");

        return $this->redirect($this->generateUrl('decks_list'));

    }

    public function deleteListAction (Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $list_id = explode('-', $request->get('ids'));

        foreach($list_id as $id)
        {
            $deck = $em->getRepository('AppBundle:Deck')->find($id);
            if(!$deck) continue;
            if ($this->getUser()->getId() != $deck->getUser()->getId()) continue;

            foreach ($deck->getChildren() as $decklist) {
                $decklist->setParent(null);
            }
            $em->remove($deck);
        }
        $em->flush();

        $this->get('session')
            ->getFlashBag()
            ->set('notice', "Decks deleted.");

        return $this->redirect($this->generateUrl('decks_list'));
    }

    public function editAction ($deck_id)
    {

        $deck = $this->getDoctrine()->getManager()->getRepository('AppBundle:Deck')->find($deck_id);

        if ($this->getUser()->getId () != $deck->getUser()->getId()) 
        {
        	return $this->render(
        		'AppBundle:Default:error.html.twig',
        		array(
        			'pagetitle' => "Error",
        			'error' => 'You are not allowed to view this deck.'
        		)
        	);
        }

        return $this->render(
        	'AppBundle:Builder:deckedit.html.twig',
        	array(
        		'pagetitle' => "Deckbuilder",
        		'deck' => $deck,
                'collection' => $this->getDoctrine()->getRepository('AppBundle:Collection')->getCollection($this->getUser()->getId())
        	)
        );

    }

    public function viewAction ($deck_id)
    {
    	$deck = $this->getDoctrine()->getManager()->getRepository('AppBundle:Deck')->find($deck_id);
    	
        if(!$deck) {
        	return $this->render(
        			'AppBundle:Default:error.html.twig',
        			array('pagetitle' => "Error",'error' => "No such deck has ever been known to the council."));}

        $is_owner = $this->getUser() && $this->getUser()->getId() == $deck->getUser()->getId();
        if(!$deck->getUser()->getIsShareDecks() && !$is_owner) {
			return $this->render('AppBundle:Default:error.html.twig',
				array('pagetitle' => "Error", 'error' => 'That deck you seek. See it you shall not.'));}

        return $this->render(
        	'AppBundle:Builder:deckview.html.twig',
        	array(
        		'pagetitle' => "Deckbuilder",
        		'deck' => $deck,
        		'deck_id' => $deck_id,
        		'is_owner' => $is_owner,
				'collection' => $this->getDoctrine()->getRepository('AppBundle:Collection')->getCollection($this->getUser()->getId()),));}

    public function compareAction($deck1_id, $deck2_id, Request $request)
    {
    	$entityManager = $this->getDoctrine()->getManager();
    
    	$deck1 = $entityManager->getRepository('AppBundle:Deck')->find($deck1_id);
    	$deck2 = $entityManager->getRepository('AppBundle:Deck')->find($deck2_id);
    
    	if(!$deck1 || !$deck2) {
    		return $this->render(
    				'AppBundle:Default:error.html.twig',
    				array(
    						'pagetitle' => "Error",
    						'error' => 'This deck cannot be found.'
    				)
    		);
    	}
    	
    	$is_owner = $this->getUser() && $this->getUser()->getId() == $deck1->getUser()->getId();
    	if(!$deck1->getUser()->getIsShareDecks() && !$is_owner) {
    		return $this->render(
    				'AppBundle:Default:error.html.twig',
    				array(
    						'pagetitle' => "Error",
    						'error' => 'You are not allowed to view this deck as the author does not have \'Make private decks unlisted\' enabled in settings.'
    				)
    		);
    	}
    	
    	$is_owner = $this->getUser() && $this->getUser()->getId() == $deck2->getUser()->getId();
    	if(!$deck2->getUser()->getIsShareDecks() && !$is_owner) {
    		return $this->render(
    				'AppBundle:Default:error.html.twig',
    				array(
    						'pagetitle' => "Error",
    						'error' => 'You are not allowed to view this deck as the author does not have \'Make private decks unlisted\' enabled in settings.'
    				)
    		);
    	}
    	 
    	$characterIntersection = $this->get('diff')->getSlotsDiff([$deck1->getSlots()->getCharacterDeck(), $deck2->getSlots()->getCharacterDeck()]);
        $battlefieldIntersection = $this->get('diff')->getSlotsDiff([$deck1->getSlots()->getBattlefieldDeck(), $deck2->getSlots()->getBattlefieldDeck()]);
    	$drawIntersection = $this->get('diff')->getSlotsDiff([$deck1->getSlots()->getDrawDeck(), $deck2->getSlots()->getDrawDeck()]);
    
    	return $this->render('AppBundle:Compare:deck_compare.html.twig', [
			'deck1' => $deck1,
			'deck2' => $deck2,
			'character_deck' => $characterIntersection,
            'battlefield_deck' => $battlefieldIntersection,
			'draw_deck' => $drawIntersection
    	]);}

public function listAction(Request $request)
{
    $user = $this->getUser();$em = $this->getDoctrine()->getManager();
    $limit = 1000; $page  = max(1, (int) $request->query->get('page', 1));
    $allDecks = $this->get('decks')->getByUser($user, false);

    $total = count($allDecks);
    if (!$total) {return $this->render('AppBundle:Builder:no-decks.html.twig', ['nbmax' => $user->getMaxNbDecks()]);}

    $lastPage = max(1, (int) ceil($total / $limit));
    if ($page > $lastPage) $page = $lastPage;

    $offset = ($page - 1) * $limit;

    $tags = [];
    foreach ($allDecks as $d) {
        if (!empty($d['tags'])) $tags[] = $d['tags'];
    }
    $tags = array_unique($tags);

    $decks = array_slice($allDecks, $offset, $limit);

    foreach ($decks as &$deck) {
        $deckEntity = $em->getRepository('AppBundle:Deck')->find($deck['id']);
        if (!$deckEntity) continue;

        $characters = [];
        foreach ($deckEntity->getSlots()->getCharacterRow() as $character) {
            $info = $this->get('cards_data')->getCardInfo($character->getCard(), false);
            $info['qty'] = $character->getQuantity();
            $info['dice'] = $character->getDice();
            $info['dices'] = $character->getDices();
            $characters[] = $info;
        } $deck['characters'] = $characters;

        $plots = [];
        foreach ($deckEntity->getSlots()->getPlotDeck() as $plot) {
            $info = $this->get('cards_data')->getCardInfo($plot->getCard(), false);
            $info['qty'] = $plot->getQuantity();
            $info['dice'] = $plot->getDice();
            $plots[] = $info;
        }
        $deck['plots'] = $plots;
    }
    unset($deck);

    $makeUrl = function (int $p) use ($request) {
        $params = $request->query->all();
        $params['page'] = $p;
        return $this->generateUrl('decks_list', $params);
    };

    $prevurl = ($page > 1) ? $makeUrl($page - 1) : null;
    $nexturl = ($page < $lastPage) ? $makeUrl($page + 1) : null;

    $window = 2;
    $start = max(1, $page - $window);
    $end   = min($lastPage, $page + $window);

    $pages = [];
    for ($p = $start; $p <= $end; $p++) {
        $pages[] = [
            'numero'  => $p,
            'url'     => $makeUrl($p),
            'current' => ($p === $page),
        ];
    }

    return $this->render('AppBundle:Builder:decks.html.twig', [
        'pagetitle' => $this->get("translator")->trans('nav.mydecks'),
        'pagedescription' => "Create custom decks with the help of a powerful deckbuilder.",
        'decks' => $decks,
        'tags' => $tags,
        'nbmax' => $user->getMaxNbDecks(),
        'nbdecks' => $total,
        'cannotcreate' => $user->getMaxNbDecks() <= $total,
        'pages' => $pages,
        'prevurl' => $prevurl,
        'nexturl' => $nexturl,
        'url' => $request->getRequestUri(),
    ]);
}

    public function copyAction ($decklist_id)
    {
        $em = $this->getDoctrine()->getManager();
        $decklist = $em->getRepository('AppBundle:Decklist')->find($decklist_id);
        $content = $decklist->getSlots()->getContent();

        return $this->forward('AppBundle:Builder:save',
                array(
                        'name' => $decklist->getName(),
                		'affiliation_code' => $decklist->getAffiliation()->getCode(),
                        'format_code' => $decklist->getFormat()->getCode(),
                        'content' => json_encode($content),
                        'decklist_id' => $decklist_id
                ));}

    public function autosaveAction(Request $request)
    {
        $user = $this->getUser();
        $deck_id = $request->get('deck_id');

        $deck = $this->getDoctrine()->getManager()->getRepository('AppBundle:Deck')->find($deck_id);
        if(!$deck) {
            throw new BadRequestHttpException("Cannot find deck ".$deck_id);
        }
        if ($user->getId() != $deck->getUser()->getId()) {
            throw new UnauthorizedHttpException("You don't have access to this deck.");
        }

        $diff = (array) json_decode($request->get('diff'));
        if (count($diff) != 2) {
        	$this->get('logger')->error("cannot use diff", $diff);
            throw new BadRequestHttpException("Wrong content ".json_encode($diff));
        }

        if(count((array)$diff[0]) || count((array)$diff[1])) {
            $change = new Deckchange();
            $change->setDeck($deck);
            $change->setVariation(json_encode($diff));
            $change->setIsSaved(FALSE);
            $this->getDoctrine()->getManager()->persist($change);
            $this->getDoctrine()->getManager()->flush();
        }
        return new Response($change->getDatecreation()->format('c'));
    }
}