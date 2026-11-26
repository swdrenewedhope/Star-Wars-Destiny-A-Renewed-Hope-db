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
		{ $response->setLastModified(max(array_map(function ($card) { return $card->getDateUpdate(); }, $rows)));
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

	public function listDecklistsByDateAction(Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json'));
		if($request->query->get('from') && $fromDate = \DateTime::createFromFormat('Y-m-d|', $request->query->get('from'))) { 
		if($request->query->get('to') && $toDate = \DateTime::createFromFormat('Y-m-d|', $request->query->get('to'))) {} else { $toDate = clone $fromDate; $toDate->modify('+1 day'); }
		$expr = Criteria::expr(); $criteria = Criteria::create()->where($expr->gte('dateCreation', $fromDate))->andWhere($expr->lt('dateCreation', $toDate));}
		if (!$criteria && !empty($request->query->all())) { $response -> setStatusCode(400); return $response; } if (!$criteria) { $criteria = Criteria::create(); }
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

	public function listDecksAction(Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Type' => 'application/json'));		
		$decks = $this->getDoctrine()->getRepository('AppBundle:Deck')->findBy(['user' => $this->getUser()]);
		if (!$decks) { $response->setContent(json_encode($decks)); return $response; }
		$versions = array_map(function ($deck) { return $deck->getId().':'.$deck->getDateUpdate()->getTimestamp(); }, $decks);
		$response->setEtag(md5(implode(',', $versions))); if ($response->isNotModified($request)) { return $response; }
		$content = json_encode($decks); $response->setContent($content); return $response;
	}
	
	public function saveDeckAction($id, Request $request)
	{
		$data = json_decode($request->getContent(), true); $request->request->replace(is_array($data) ? $data : []);		
		$user = $this->get('fos_user.user_manager')->findUserByUsername($this->getUser());
		$deck = $id ? $this->getDoctrine()->getRepository('AppBundle:Deck')->find($id): (new Deck())-> setUser($user);
		if($deck->getUser()->getId() !== $user->getId()) { return new Response('', 403); }; $this->getDoctrine()->getManager()->persist($deck);
		$affiliation = $this->getDoctrine()->getManager()->getRepository('AppBundle:Affiliation')->findOneBy(['code' => $request->get('affiliation_code')]);
		$format = $this->getDoctrine()->getManager()->getRepository('AppBundle:Format')->findOneBy(['code' => $request->get('format_code')]);
		$slots = (array) $request->get('slots'); $name = $request->get('name') ?: $deck->getName() ?: 'New Deck';
		$decklist_id = $request->get('decklist_id'); $description = trim($request->get('description')); $tags = $request->get('tags');

		if (!$affiliation || !$format || !count($slots)) { return new Response('', 400); } foreach($slots as $card_code => $qty)
			{ if(!is_array($qty) || !is_integer($qty['quantity'] ?? null) || !is_integer($qty['dice'] ?? 0)) {return new Response('', 400);}}
		
		$this->get('decks')->saveDeck($this->getUser(), $deck, $decklist_id, $name, $affiliation, $format, $description, $tags, $slots, null);
		$this->getDoctrine()->getManager()->flush(); return new JsonResponse($deck->getId());
	}

	public function deleteDeckAction($id) 
	{
		$deck = $this->getDoctrine()->getManager()->getRepository('AppBundle:Deck')->find($id); if(!$deck) { return new Response('', 404); }
		if($deck->getUser()->getId() !== $this->getUser()->getId()) { return new Response('', 403); }
		foreach($deck->getChildren() as $decklist) { $decklist->setParent(null); }
		$this->getDoctrine()->getManager()->remove($deck); $this->getDoctrine()->getManager()->flush(); return new Response('', 200);
	}

	public function publishDeckAction($id)
	{
		$deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($id); if (!$deck) { return new Response('', 404); }
		if ($this->getUser()->getId() !== $deck->getUser()->getId()) { return new Response('', 403); }
		$yesterday = (new \DateTime())->modify('-24 hours'); 
		$query = $this->getDoctrine()->getManager()->createQuery("SELECT COUNT(d) FROM AppBundle:Decklist d WHERE d.dateCreation>:date AND d.user=:user");
        $query->setParameter('date', $yesterday); $query->setParameter('user', $deck->getUser()); $decklistsSinceYesterday = $query->getSingleScalarResult();
        if($decklistsSinceYesterday >= 3 && $deck->getUser()->getDonation() <= 0) { return new Response('', 429);}
        try { $decklist = $this->get('decklist_factory')->createDecklistFromDeck($deck, $deck->getName(), $deck->getDescriptionMd()); }
        catch(\Exception $e) { return new Response('', 422); } $this->getDoctrine()->getManager()->persist($decklist); 
		$this->getDoctrine()->getManager()->flush(); return new Response('', 200);
    }
}