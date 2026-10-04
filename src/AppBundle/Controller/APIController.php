<?php
namespace AppBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\Decklist;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use AppBundle\Entity\Deck;
use Symfony\Component\HttpFoundation\JsonResponse;

class APIController extends Controller
{
    function showDocsAction () { return $this->render('AppBundle:API:API.html.twig', [], new Response()); }

	public function listFormatsAction(Request $request)
	{
		$response = (new Response('', 200, ['Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json']))->setPublic()->setMaxAge($this->container->getParameter('cache_expiration'));	
		$list_formats = $this->getDoctrine()->getRepository('AppBundle:Format')->findAll();
		$lastModified = max(array_map(function ($format) { return $format->getDateUpdate(); }, $list_formats));
		$response->setLastModified($lastModified); if ($response->isNotModified($request)) { return $response; }
		foreach($list_formats as $format) { $formats[] = array( "name" => $format->getName(), "code" => $format->getCode(), "data" => $format->getData()); }
		$content = json_encode($formats); $response->setContent($content); return $response;
	}

	public function listSetsAction(Request $request)
	{
		$response = (new Response('', 200, ['Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json']))->setPublic()->setMaxAge($this->container->getParameter('cache_expiration'));	
		$list_sets = $this->getDoctrine()->getRepository('AppBundle:Set')->findAll(); $sets = array();
		$lastModified = max(array_map(function ($set) { return $set->getDateUpdate(); }, $list_sets));
		$response->setLastModified($lastModified); if ($response->isNotModified($request)) { return $response; }
		foreach($list_sets as $set) {
			$real = count($set->getCards());
			$max = $set->getSize();
			$sets[] = array(
					"name" => $set->getName(),
					"code" => $set->getCode(),
					"position" => $set->getPosition(),
					"available" => $set->getDateRelease() ? $set->getDateRelease()->format('Y-m-d') : '',
					"known" => intval($real),
					"total" => $max,
					"url" => $this->get('router')->generate('cards_list', array('set_code' => $set->getCode()), UrlGeneratorInterface::ABSOLUTE_URL),
			);} $content = json_encode($sets); $response->setContent($content); return $response;
	}

	public function getCardAction($card_code, Request $request)
	{
		$response = (new Response('', 200, ['Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json']))->setPublic()->setMaxAge($this->container->getParameter('cache_expiration'));	
		$card = $this->getDoctrine()->getRepository('AppBundle:Card')->findOneBy(array("code" => $card_code));
		if(!$card) { $response -> setStatusCode(404); return $response; }
		$response->setLastModified($card->getDateUpdate()); if ($response->isNotModified($request)) { return $response; }
		$cardData = $this->get('cards_data')->getCardInfo($card, true, "en");
		$content = json_encode($cardData); $response->setContent($content); return $response;
	}

	public function listAllCardsAction(Request $request)
	{
		$response = (new Response('', 200, ['Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json']))->setPublic()->setMaxAge($this->container->getParameter('cache_expiration'));	
		$list_cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findAll(); $cards = array();
		$lastModified = max(array_map(function ($card) { return $card->getDateUpdate(); }, $list_cards));
		$response->setLastModified($lastModified); if ($response->isNotModified($request)) { return $response; }
		foreach($list_cards as $card) { $cards[] = $this->get('cards_data')->getCardInfo($card, true, 'en'); }
		$content = json_encode($cards); $response->setContent($content); return $response;
	}

	public function listCardsBySetAction($set_code, Request $request)
	{
		$response = (new Response('', 200, ['Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json']))->setPublic()->setMaxAge($this->container->getParameter('cache_expiration'));	
		$set = $this->getDoctrine()->getRepository('AppBundle:Set')->findOneBy(array('code' => $set_code));
		if (!$set) { $response -> setStatusCode(404); return $response; }
		$conditions = $this->get('cards_data')->syntax("s:$set_code");
		$this->get('cards_data')->validateConditions($conditions);
		$query = $this->get('cards_data')->buildQueryFromConditions($conditions); $cards = array();
		if($query && $rows = $this->get('cards_data')->get_search_rows($conditions, "set"))
		{
			$lastModified = max(array_map(function ($card) { return $card->getDateUpdate(); }, $rows));
			$response->setLastModified($lastModified); if ($response->isNotModified($request)) { return $response; }
			for($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				$card = $this->get('cards_data')->getCardInfo($rows[$rowindex], true, "en"); $cards[] = $card;
			}
		} $content = json_encode($cards); $response->setContent($content); return $response;
	}

	public function findCardsAction(Request $request)
	{
		$response = (new Response('', 200, ['Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json']))->setPublic()->setMaxAge($this->container->getParameter('cache_expiration'));	
		$q = $request->query->get('q'); $conditions = $this->get('cards_data')->syntax($q);
		$this->get('cards_data')->validateConditions($conditions);
		$query = $this->get('cards_data')->buildQueryFromConditions($conditions); $cards = array();
		if($query && $rows = $this->get('cards_data')->get_search_rows($conditions, "set"))
		{ $response->setLastModified($this->get('cards_data')->getRowsLastModified($rows));
        	if ($response->isNotModified($request)) { return $response; }

			for($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				$card = $this->get('cards_data')->getCardInfo($rows[$rowindex], true, 'en');
				$cards[] = $card;
			}
		} $content = json_encode($cards); $response->setContent($content); return $response;
	}

	public function getDecklistAction($decklist_id, Request $request)
	{
		$response = (new Response('', 200, ['Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json']))->setPublic()->setMaxAge($this->container->getParameter('cache_expiration'));	
		$decklist = $this->getDoctrine()->getRepository('AppBundle:Decklist')->find($decklist_id);
		if(!$decklist) { $response -> setStatusCode(404); return $response; }
		$response->setLastModified($decklist->getDateUpdate()); if ($response->isNotModified($request)) { return $response; }
		$content = json_encode($decklist); $response->setContent($content); return $response;
	}

	public function listDecklistsByDateAction($date, Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json'));	
		$start = \DateTime::createFromFormat('Y-m-d|', $date); $end = (clone $start)->modify('+1 day');
		$expr = Criteria::expr();
		$criteria = Criteria::create()->where($expr->gte('dateCreation', $start))->andWhere($expr->lt('dateCreation', $end));
		$decklists = iterator_to_array($this->getDoctrine()->getRepository('AppBundle:Decklist')->matching($criteria));
		$content = json_encode($decklists); $response->setContent($content); return $response;
	}

	public function getDeckAction($deck_id, Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json'));
		$deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($deck_id);
    	if (!$deck) { $response -> setStatusCode(404); return $response; }
		$isOwner = $this->getUser() && $deck->getUser()->getId() === $this->getUser()->getId();
		if (!$deck->getUser()->getIsShareDecks() && !$isOwner) { $response -> setStatusCode(403); return $response; };
		$response->setLastModified($deck->getDateUpdate()); if ($response->isNotModified($request)) { return $response; }
		$content = json_encode($deck); $response->setContent($content); return $response;
	}



	# OAUTH API FUNCTIONS

	public function listDecksAction(Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json'));		
		$decks = $this->getDoctrine()->getRepository('AppBundle:Deck')->findBy(['user' => $this->getUser()]);
		if (!$decks) { $response->setContent(json_encode($decks)); return $response; }
		$dateUpdates = array_map(function ($deck) { return $deck->getDateUpdate(); }, $decks);
		$response->setLastModified(max($dateUpdates));
		if ($response->isNotModified($request)) { return $response; }
		$content = json_encode($decks); $response->setContent($content); return $response;
	}
	
	public function saveDeckAction($id, Request $request)
	{
		if(!$id) { $deck = new Deck(); $this->getDoctrine()->getManager()->persist($deck); }
		else
		{ $deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($id);
			if($deck->getUser()->getId() !== $this->getUser()->getId()) { $response -> setStatusCode(403); return $response; }
		}
		
		$affiliation_code = filter_var($request->get('affiliation_code'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		if(!$affiliation_code) {
			return new JsonResponse([
					'success' => FALSE,
					'msg' => "Affiliation code missing"
			]);
		}
		$affiliation = $this->getDoctrine()->getManager()->getRepository('AppBundle:Affiliation')->findOneBy(['code' => $affiliation_code]);
		if(!$affiliation) {
			return new JsonResponse([
					'success' => FALSE,
					'msg' => "Affiliation code invalid"
			]);
		}
		$format = $this->getDoctrine()->getRepository('AppBundle:Format')->findOneBy(['code' => 'INF']);
		$slots = (array) json_decode($request->get('slots'));
		if (!count($slots)) {
			return new JsonResponse([
					'success' => FALSE,
					'msg' => "Slots missing"
			]);
		}
		foreach($slots as $card_code => $qty)
		{
			if(!is_string($card_code) || !is_integer($qty))
			{
				return new JsonResponse([
						'success' => FALSE,
						'msg' => "Slots invalid"
				]);
			}
		}
		
		$name = filter_var($request->get('name'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		if(!$name) {
			return new JsonResponse([
					'success' => FALSE,
					'msg' => "Name missing"
			]);
		}
		
		$decklist_id = filter_var($request->get('decklist_id'), FILTER_SANITIZE_NUMBER_INT);
		$description = trim($request->get('description'));
		$tags = filter_var($request->get('tags'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		
		$this->get('decks')->saveDeck($this->getUser(), $deck, $decklist_id, $name, $affiliation, $format, $description, $tags, $slots, null);
		
		$this->getDoctrine()->getManager()->flush();
		
		return new JsonResponse([
				'success' => TRUE,
				'msg' => $deck->getId()
		]);
	}

	public function publishDeckAction($id, Request $request)
	{
		$deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($id);
		if ($this->getUser()->getId() !== $deck->getUser()->getId()) {
			throw $this->createAccessDeniedException("Access denied to this object.");
		}
		
		$name = filter_var($request->request->get('name'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		$descriptionMd = trim($request->request->get('description_md'));
		
		$tournament_id = intval(filter_var($request->request->get('tournament_id'), FILTER_SANITIZE_NUMBER_INT));
		$tournament = $this->getDoctrine()->getManager()->getRepository('AppBundle:Tournament')->find($tournament_id);

		$precedent_id = trim($request->request->get('precedent'));
		if(!preg_match('/^\d+$/', $precedent_id)) 
		{
			if(preg_match('/view\/(\d+)/', $precedent_id, $matches)) 
			{
				$precedent_id = $matches[1];
			}
			else
			{
				$precedent_id = null;
			}
		}
		$precedent = $precedent_id ? $em->getRepository('AppBundle:Decklist')->find($precedent_id) : null;
		
        try 
        {
        	$decklist = $this->get('decklist_factory')->createDecklistFromDeck($deck, $name, $descriptionMd);
        }
        catch(\Exception $e)
        {
        	return new JsonResponse([
        			'success' => FALSE,
        			'msg' => $e->getMessage()
        	]);
        }
        
        $decklist->setTournament($tournament);
        $decklist->setPrecedent($precedent);
        $this->getDoctrine()->getManager()->persist($decklist);
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse([
        		'success' => TRUE,
        		'msg' => $decklist->getId()
        ]);
    }
}
