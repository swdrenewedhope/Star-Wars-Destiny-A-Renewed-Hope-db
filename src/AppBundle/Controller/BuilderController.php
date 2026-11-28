<?php
namespace AppBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use AppBundle\Entity\Deck;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Deckchange;


// Check these actually aren't used.

use AppBundle\Entity\Deckslot;
use AppBundle\Entity\Card;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DomCrawler\Crawler;

class BuilderController extends Controller
{
	public function buildformAction()
	{ $response = new Response(); $response->setPublic(); $response->setMaxAge($this->container->getParameter('cache_expiration'));
		return $this->render('AppBundle:Builder:initbuild.html.twig', [ 
                'affiliations' => $this->getDoctrine()->getManager()->getRepository('AppBundle:Affiliation')->findPrimaries(),
                'formats' => $this->getDoctrine()->getManager()->getRepository('AppBundle:Format')->findAll()], $response);}

    public function initbuildAction (Request $request)
    {
        $translator = $this->get('translator'); $em = $this->getDoctrine()->getManager();
        $affiliation = $em->getRepository('AppBundle:Affiliation')->findByCode($request->request->get('affiliation'));
        if(!$affiliation){$this->get('session')->getFlashBag()->set('error', $translator->trans("decks.build.errors.noaffiliation"));
        	return $this->redirect($this->generateUrl('deck_buildform'));}

        $format = $em->getRepository('AppBundle:Format')->findByCode('INF');
        $name = $translator->trans("decks.build.newname", array("%affiliation%" => $affiliation->getName()));
        $deck = new Deck(); $deck->setDescriptionMd("This deck's fate is not yet written.");
        $deck->setAffiliation($affiliation); $deck->setFormat($format);
        $deck->setName($name); $deck->setProblem('incorrect_size'); $deck->setUser($this->getUser());
        $deck->setTags(join(' ', array_unique([$request->request->get('affiliation'), $format->getName()])));
        $em->persist($deck); $em->flush();
        return $this->redirect($this->get('router')->generate('deck_edit', ['deck_id' => $deck->getId()]));}

    public function fileimportAction (Request $request)
{
    $uploadedFile = $request->files->get('upfile');
    if (!isset($uploadedFile)) { return new Response('No file provided.', 400, array('Refresh' => '3; url=/decks')); }
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

    public function textexportAction ($deck_id)
    {
        $em = $this->getDoctrine()->getManager();
        $deck = $em->getRepository('AppBundle:Deck')->find($deck_id);
        $is_owner = $this->getUser() && $this->getUser()->getId() == $deck->getUser()->getId();
        if(!$deck->getUser()->getIsShareDecks() && !$is_owner) {
        	return $this->render(
        			'AppBundle:Default:error.html.twig',
        			array('pagetitle' => "Error", 'error' => 'That deck you seek. See it you shall not.'));}

        $factionNames = [];
        foreach($this->getDoctrine()->getRepository('AppBundle:Faction')->findAllAndOrderByName() as $faction) {
            $factionNames[$faction->getCode()] = $faction->getName();}

        $content = $this->renderView('AppBundle:Export:plain.txt.twig', ["deck" => $deck->getTextExport(), "factionNames" => $factionNames]);
        $content = str_replace("\n", "\r\n", $content);

		$response = new Response(); $response->headers->set('Content-Type', 'text/plain');
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
        if(!$deck->getUser()->getIsShareDecks() && !$is_owner) { return new Response('', 403);}

        $content = $deck->getSlots()->getContent();
        return $this->forward('AppBundle:Builder:save',
            array('name' => $deck->getName().' (clone)',
                'affiliation_code' => $deck->getAffiliation()->getCode(),
                'format_code' => $deck->getFormat()->getCode(),
                'content' => json_encode($content),
                'deck_id' => $deck->getParent() ? $deck->getParent()->getId() : null));}

    public function saveAction (Request $request)
    {
        $em = $this->getDoctrine()->getManager(); $user = $this->getUser();
        if (count($user->getDecks()) > $user->getMaxNbDecks()) return new Response('', 409);
        $id = $request->get('id'); $deck = null; $source_deck = null;

        if($id) { $deck = $em->getRepository('AppBundle:Deck')->find($id);
            if (!$deck || $user->getId() != $deck->getUser()->getId()) { return new Response('', 403); }
            $source_deck = $deck; }

		$affiliation_code = $em->getRepository('AppBundle:Affiliation')->findOneBy(['code' => $request->get('affiliation_code')]);
		if(!$affiliation_code) { return new Response('Unknown affiliation code.', 422, array('Refresh' => '3; url=/decks')); }
        $format_code = $em->getRepository('AppBundle:Format')->findOneBy(['code' => $request->get('format_code') ?: 'INF']); // The default INF is kept to not invalidate existing deck files. Historically format wasn't saved.

        $cancel_edits = (bool) $request->get('cancel_edits'); if($cancel_edits) 
		{ if($deck) $this->get('decks')->revertDeck($deck); return $this->redirect($this->generateUrl('decks_list'));}

        $is_copy = (bool) $request->get('copy'); if($is_copy || !$id) { $deck = new Deck(); }

        $content = (array) json_decode($request->get('content'), true);
        if (!count($content)) { return new Response('Deck is empty!', 400, array('Refresh' => '3; url=/decks')); }

        $name = $request->get('name'); $decklist_id = $request->get('decklist_id');
        $description = trim($request->get('description')); $tags = $request->get('tags');

        $this->get('decks')->saveDeck($this->getUser(), $deck, $decklist_id, $name, $affiliation_code, $format_code, $description, $tags, $content, $source_deck ? $source_deck : null);
        $em->flush(); return $this->redirect($this->generateUrl('decks_list'));
    }

    public function deleteAction (Request $request)
    {
        $deck = $this->getDoctrine()->getManager()->getRepository('AppBundle:Deck')->find($request->get('deck_id'));
		if (!$deck) return $this->redirect($this->generateUrl('decks_list'));
        if ($this->getUser()->getId() != $deck->getUser()->getId()) {
        	return $this->render('AppBundle:Default:error.html.twig',
        			array('error' => 'That deck you seek. See it you shall not.'), new Response('', 403));}

        foreach ($deck->getChildren() as $decklist) {
            $decklist->setParent(null);
        }
        $this->getDoctrine()->getManager()->remove($deck); $this->getDoctrine()->getManager()->flush();

        $this->get('session')->getFlashBag()->set('notice', "Deck deleted.");

        return $this->redirect($this->generateUrl('decks_list'));

    }

    public function deleteListAction (Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $list_id = explode('-', $request->get('ids'));
        foreach($list_id as $id)
        { $deck = $em->getRepository('AppBundle:Deck')->find($id);
            if(!$deck) continue;
            if ($this->getUser()->getId() != $deck->getUser()->getId()) continue;
            foreach ($deck->getChildren() as $decklist) { $decklist->setParent(null); }
            $em->remove($deck);
        } $em->flush();
        $this->get('session')->getFlashBag()->set('notice', "Decks deleted.");
        return $this->redirect($this->generateUrl('decks_list'));
    }

    public function editAction ($deck_id)
    {
        $deck = $this->getDoctrine()->getManager()->getRepository('AppBundle:Deck')->find($deck_id);
        if ($this->getUser()->getId () != $deck->getUser()->getId()) 
        { return $this->render('AppBundle:Default:error.html.twig',
        		array('pagetitle' => "Error",'error' => 'That deck you seek. See it you shall not.'));}

        return $this->render('AppBundle:Builder:deckedit.html.twig',
        	array('deck' => $deck, 'collection' => $this->getDoctrine()->getRepository('AppBundle:Collection')->getCollection($this->getUser()->getId())));
	}

    public function viewAction ($deck_id)
    {
    	$deck = $this->getDoctrine()->getManager()->getRepository('AppBundle:Deck')->find($deck_id);
        if(!$deck) { return $this->render('AppBundle:Default:error.html.twig',
        		array('pagetitle' => "Error",'error' => "No such deck has ever been known to the council."));}

        $is_owner = $this->getUser() && $this->getUser()->getId() == $deck->getUser()->getId();
        if(!$deck->getUser()->getIsShareDecks() && !$is_owner) {
			return $this->render('AppBundle:Default:error.html.twig',
				array('pagetitle' => "Error", 'error' => 'That deck you seek. See it you shall not.'));}

        return $this->render('AppBundle:Builder:deckview.html.twig',
        	array('deck' => $deck,'deck_id' => $deck_id,'is_owner' => $is_owner,
				'collection' => $this->getDoctrine()->getRepository('AppBundle:Collection')->getCollection($this->getUser()->getId()),));}

    public function compareAction($deck1_id, $deck2_id)
    {
    	$entityManager = $this->getDoctrine()->getManager();
    	$deck1 = $entityManager->getRepository('AppBundle:Deck')->find($deck1_id);
    	$deck2 = $entityManager->getRepository('AppBundle:Deck')->find($deck2_id);
    
    	if(!$deck1 || !$deck2) { return $this->render('AppBundle:Default:error.html.twig', 
			array('error' => 'No such deck has ever been known to the council.'));}
    	
    	$is_owner = $this->getUser() && $this->getUser()->getId() == $deck1->getUser()->getId();
    	if(!$deck1->getUser()->getIsShareDecks() && !$is_owner) {
    		return $this->render('AppBundle:Default:error.html.twig',
    				array('error' => 'That deck you seek. See it you shall not.'));}
    	
    	$is_owner = $this->getUser() && $this->getUser()->getId() == $deck2->getUser()->getId();
    	if(!$deck2->getUser()->getIsShareDecks() && !$is_owner) {
    		return $this->render('AppBundle:Default:error.html.twig',
    				array('error' => 'That deck you seek. See it you shall not.'));}
    	 
    	$characterIntersection = $this->get('diff')->getSlotsDiff([$deck1->getSlots()->getCharacterDeck(), $deck2->getSlots()->getCharacterDeck()]);
        $battlefieldIntersection = $this->get('diff')->getSlotsDiff([$deck1->getSlots()->getBattlefieldDeck(), $deck2->getSlots()->getBattlefieldDeck()]);
    	$drawIntersection = $this->get('diff')->getSlotsDiff([$deck1->getSlots()->getDrawDeck(), $deck2->getSlots()->getDrawDeck()]);
    
    	return $this->render('AppBundle:Compare:deck_compare.html.twig', [
			'deck1' => $deck1,'deck2' => $deck2,
			'character_deck' => $characterIntersection,
            'battlefield_deck' => $battlefieldIntersection,
			'draw_deck' => $drawIntersection]);}

public function listAction(Request $request)
{
    $user = $this->getUser(); $em = $this->getDoctrine()->getManager(); $decks = $this->get('decks')->getByUser($user, false);
    if (!count($decks)) {return $this->render('AppBundle:Builder:no-decks.html.twig', ['nbmax' => $user->getMaxNbDecks()]);}
    foreach ($decks as &$deck) {
		if (!empty($deck['tags'])) $tags[] = $deck['tags'];
        $deckEntity = $em->getRepository('AppBundle:Deck')->find($deck['id']);
        $characters = [];
        foreach ($deckEntity->getSlots()->getCharacterRow() as $character) {
            $info = $this->get('cards_data')->getCardInfo($character->getCard(), false);
            $info['qty'] = $character->getQuantity();
            $info['dice'] = $character->getDice();
            $info['dices'] = $character->getDices();
            $characters[] = $info; } $plots = [];
        foreach ($deckEntity->getSlots()->getPlotDeck() as $plot) {
            $info = $this->get('cards_data')->getCardInfo($plot->getCard(), false);
            $info['qty'] = $plot->getQuantity(); $info['dice'] = $plot->getDice();
			$plots[] = $info; }
		$deck['characters'] = $characters; $deck['plots'] = $plots;
    } unset($deck);

    return $this->render('AppBundle:Builder:decks.html.twig', [
        'decks' => $decks,'tags' => $tags,
        'nbmax' => $user->getMaxNbDecks(),
        'nbdecks' => count($decks),
        'cannotcreate' => $user->getMaxNbDecks() <= count($decks),
		'prevurl' => null,'pages' => null,
		'nexturl'=> null, 'url' => $request->getRequestUri()]);}

    public function copyAction ($decklist_id)
    {
        $decklist = $this->getDoctrine()->getManager()->getRepository('AppBundle:Decklist')->find($decklist_id);
        $content = $decklist->getSlots()->getContent(); return $this->forward('AppBundle:Builder:save',
                array( 'name' => $decklist->getName(),
                	'affiliation_code' => $decklist->getAffiliation()->getCode(),
                    'format_code' => $decklist->getFormat()->getCode(),
                    'content' => json_encode($content),
                    'decklist_id' => $decklist_id));}

    public function autosaveAction(Request $request)
    {
        $user = $this->getUser(); $deck_id = $request->get('deck_id'); $diff = (array) json_decode($request->get('diff'));
        $deck = $this->getDoctrine()->getManager()->getRepository('AppBundle:Deck')->find($deck_id);
        if ($user->getId() != $deck->getUser()->getId()) { return new Response('', 403); }
        if(count((array)$diff[0]) || count((array)$diff[1])) {
            $change = new Deckchange();$change->setDeck($deck);
            $change->setVariation(json_encode($diff));
            $change->setIsSaved(FALSE);
            $this->getDoctrine()->getManager()->persist($change);
            $this->getDoctrine()->getManager()->flush();
        } return new Response($change->getDatecreation()->format('c'));}}