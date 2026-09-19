<?php
namespace AppBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\Decklist;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiController extends Controller
{
    function showDocsAction () { return $this->render('AppBundle:API:API.html.twig', [], new Response()); }

	public function listFormatsAction(Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
		$list_formats = $this->getDoctrine()->getRepository('AppBundle:Format')->findAll();
		$formats = array();

		foreach($list_formats as $format) {
			$formats[] = array(
					"name" => $format->getName(),
					"code" => $format->getCode(),
					"data" => $format->getData()
			);
		}

		$content = json_encode($formats);	
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}

	public function listSetsAction(Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
		$list_sets = $this->getDoctrine()->getRepository('AppBundle:Set')->findAll();
		$sets = array();

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
			);
		}

		$content = json_encode($sets);
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}

	public function getCardAction($card_code, Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
		$cardLookup = $this->getDoctrine()->getRepository('AppBundle:Card')->findOneBy(array("code" => $card_code));
		if(!$cardLookup) { $response -> setStatusCode(404); return $response; }
		$cardData = $this->get('cards_data')->getCardInfo($cardLookup, true, "en");
		$content = json_encode($cardData);	
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}

	public function listAllCardsAction(Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
		$list_cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findAll();
		$cards = array();
		foreach($list_cards as $card) { $cards[] = $this->get('cards_data')->getCardInfo($card, true, 'en'); }
		$content = json_encode($cards);
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}

	public function listCardsBySetAction($set_code, Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
		$set = $this->getDoctrine()->getRepository('AppBundle:Set')->findOneBy(array('code' => $set_code));
		if (!$set) { $response -> setStatusCode(404); return $response; }
		$response->headers->set('Content-Type', 'application/json');		
		$conditions = $this->get('cards_data')->syntax("s:$set_code");
		$this->get('cards_data')->validateConditions($conditions);
		$query = $this->get('cards_data')->buildQueryFromConditions($conditions);
		$cards = array();

		if($query && $rows = $this->get('cards_data')->get_search_rows($conditions, "set"))
		{
			for($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				$card = $this->get('cards_data')->getCardInfo($rows[$rowindex], true, "en");
				$cards[] = $card;
			}
		}

		$content = json_encode($cards);
		$response->setContent($content);
		return $response;
	}

	public function findCardsAction(Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
		$q = $request->query->get('q');
		$conditions = $this->get('cards_data')->syntax($q);
		$this->get('cards_data')->validateConditions($conditions);
		$query = $this->get('cards_data')->buildQueryFromConditions($conditions);
		$cards = array();

		if($query && $rows = $this->get('cards_data')->get_search_rows($conditions, "set"))
		{
			for($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				$card = $this->get('cards_data')->getCardInfo($rows[$rowindex], true, 'en');
				$cards[] = $card;
			}
		}

		if (!$cards) {$response -> setStatusCode(204); return $response;}
		$content = json_encode($cards);
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}

	public function getDecklistAction($decklist_id, Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
		$decklist = $this->getDoctrine()->getRepository('AppBundle:Decklist')->find($decklist_id);
		if(!$decklist) { $response -> setStatusCode(404); return $response; }
		$content = json_encode($decklist);	
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}

	public function listDecklistsByDateAction($date, Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
		$start = \DateTime::createFromFormat('Y-m-d', $date);
		$start->setTime(0, 0, 0);
		$end = clone $start;
		$end->add(new \DateInterval("P1D"));
		$expr = Criteria::expr();
		$criteria = Criteria::create();
		$criteria->where($expr->gte('dateCreation', $start));
		$criteria->andWhere($expr->lt('dateCreation', $end));
		$decklists = iterator_to_array($this->getDoctrine()->getRepository('AppBundle:Decklist')->matching($criteria));
		if(!$decklists) { $response -> setStatusCode(404); return $response; }
		$content = json_encode($decklists);
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}

	public function getDeckAction($deck_id, Request $request)
	{
		$response = new Response('', 200, array('Access-Control-Allow-Origin' => '*', 'Content-Language' => 'en'));
    	$deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($deck_id);
    	if (!$deck) { $response -> setStatusCode(404); return $response; }
 		if (!$deck->getUser()->getIsShareDecks()) { $response -> setStatusCode(403); return $response; };
    	$content = json_encode($deck);
    	$response->headers->set('Content-Type', 'application/json');
    	$response->setContent($content);
    	return $response;
	}
}
