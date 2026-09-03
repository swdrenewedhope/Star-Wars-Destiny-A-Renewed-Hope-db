<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\Decklist;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\Criteria;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiController extends Controller
{
	private function createResponse(Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
			'Access-Control-Allow-Origin' => '*',
			'Content-Language' => $request->getLocale()
		));

		return $response;
	}

	public function listFormatsAction(Request $request)
	{
		$response = $this->createResponse($request);
		$jsonp = $request->query->get('jsonp');
		$list_formats = $this->getDoctrine()->getRepository('AppBundle:Format')->findAll();
		$lastModified = NULL;

		foreach($list_formats as $format) {
			if(!$lastModified || $lastModified < $format->getDateUpdate()) {
				$lastModified = $format->getDateUpdate();
			}
		}

		$response->setLastModified($lastModified);
		
		if ($response->isNotModified($request)) { return $response; }

		$formats = array();

		foreach($list_formats as $format) {
			$formats[] = array(
					"name" => $format->getName(),
					"code" => $format->getCode(),
					"data" => $format->getData()
			);
		}

		$content = json_encode($formats);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;
	}

	public function listSetsAction(Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
			'Access-Control-Allow-Origin' => '*',
			'Content-Language' => $request->getLocale()
		));

		$jsonp = $request->query->get('jsonp');
		$list_sets = $this->getDoctrine()->getRepository('AppBundle:Set')->findAll();
		$lastModified = NULL;

		foreach($list_sets as $set) {
			if(!$lastModified || $lastModified < $set->getDateUpdate()) {
				$lastModified = $set->getDateUpdate();
			}
		}
		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

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
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;
	}

	public function getCardAction($card_code, Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
			'Access-Control-Allow-Origin' => '*',
			'Content-Language' => $request->getLocale()
		));

		$jsonp = $request->query->get('jsonp');
		$card = $this->getDoctrine()->getRepository('AppBundle:Card')->findOneBy(array("code" => $card_code));
		$lastModified = NULL;

		if(!$lastModified || $lastModified < $card->getDateUpdate()) {
			$lastModified = $card->getDateUpdate();
		}
		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		$card = $this->get('cards_data')->getCardInfo($card, true, "en");
		$content = json_encode($card);

		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;

	}

	public function listCardsAction(Request $request)
	{
		$locale = $request->getLocale();

		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
			'Access-Control-Allow-Origin' => '*',
			'Content-Language' => $locale
		));

		$jsonp = $request->query->get('jsonp');
		$list_cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findAll();
		$lastModified = NULL;

		foreach($list_cards as $card) {
			if(!$lastModified || $lastModified < $card->getDateUpdate()) {
				$lastModified = $card->getDateUpdate();
			}
		}
		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		$cards = array();

		foreach($list_cards as $card) {
			$cards[] = $this->get('cards_data')->getCardInfo($card, true, $locale);
		}

		$content = json_encode($cards);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;

	}

	public function listCardsBySetAction($set_code, Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));

		$jsonp = $request->query->get('jsonp');

		$format = $request->getRequestFormat();
		if($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');
			return $response;
		}

		$set = $this->getDoctrine()->getRepository('AppBundle:Set')->findOneBy(array('code' => $set_code));
		if(!$set) die();

		$conditions = $this->get('cards_data')->syntax("s:$set_code");
		$this->get('cards_data')->validateConditions($conditions);
		$query = $this->get('cards_data')->buildQueryFromConditions($conditions);

		$cards = array();
		$last_modified = null;
		if($query && $rows = $this->get('cards_data')->get_search_rows($conditions, "set"))
		{
			for($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				if(empty($last_modified) || $last_modified < $rows[$rowindex]->getDateUpdate()) $last_modified = $rows[$rowindex]->getDateUpdate();
			}
			$response->setLastModified($last_modified);
			if ($response->isNotModified($request)) {
				return $response;
			}
			for($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				$card = $this->get('cards_data')->getCardInfo($rows[$rowindex], true, "en");
				$cards[] = $card;
			}
		}

		$content = json_encode($cards);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);

		return $response;
	}

	public function findCardsAction(Request $request)
	{
		$locale = $request->getLocale();

		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
			'Access-Control-Allow-Origin' => '*',
			'Content-Language' => $locale
		));

		$jsonp = $request->query->get('jsonp');
		$q = $request->query->get('q');

		$conditions = $this->get('cards_data')->syntax($q);
		$this->get('cards_data')->validateConditions($conditions);
		$query = $this->get('cards_data')->buildQueryFromConditions($conditions);

		$cards = array();
		$last_modified = null;
		if($query && $rows = $this->get('cards_data')->get_search_rows($conditions, "set"))
		{
			for($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				if(empty($last_modified) || $last_modified < $rows[$rowindex]->getDateUpdate()) $last_modified = $rows[$rowindex]->getDateUpdate();
			}
			$response->setLastModified($last_modified);
			if ($response->isNotModified($request)) {
				return $response;
			}
			for($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				$card = $this->get('cards_data')->getCardInfo($rows[$rowindex], true, $locale);
				$cards[] = $card;
			}
		}

		$content = json_encode($cards);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;
	}

	public function getDecklistAction($decklist_id, Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));
		
		$jsonp = $request->query->get('jsonp');
		
		$format = $request->getRequestFormat();
		if($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');
			return $response;
		}
		
		/* @var $decklist \AppBundle\Entity\Decklist */
		$decklist = $this->getDoctrine()->getRepository('AppBundle:Decklist')->find($decklist_id);
		if(!$decklist) die();
		
		$response->setLastModified($decklist->getDateUpdate());
		if ($response->isNotModified($request)) {
			return $response;
		}
		
		$content = json_encode($decklist);
		
		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}
		
		$response->setContent($content);
		return $response;
		
	}

	public function listDecklistsByDateAction($date, Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));
		
		$jsonp = $request->query->get('jsonp');
		
		$format = $request->getRequestFormat();
		if($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');
			return $response;
		}
		
		$start = \DateTime::createFromFormat('Y-m-d', $date);
		$start->setTime(0, 0, 0);
		$end = clone $start;
		$end->add(new \DateInterval("P1D"));
		
		$expr = Criteria::expr();
		$criteria = Criteria::create();
		$criteria->where($expr->gte('dateCreation', $start));
		$criteria->andWhere($expr->lt('dateCreation', $end));
		
		/* @var $decklists \Doctrine\Common\Collections\ArrayCollection */
		$decklists = $this->getDoctrine()->getRepository('AppBundle:Decklist')->matching($criteria);
		if(!$decklists) die();
		
		$dateUpdates = $decklists->map(function ($decklist) {
			return $decklist->getDateUpdate();
		})->toArray();
		
		$response->setLastModified(max($dateUpdates));
		if ($response->isNotModified($request)) {
			return $response;
		}
		
		$content = json_encode($decklists);
		
		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}
		
		$response->setContent($content);
		return $response;
		
	}

public function getDeckAction($deck_id, Request $request)
{
    $response = new Response();
    $response->setPublic();
    $response->setMaxAge($this->container->getParameter('cache_expiration'));
    $response->headers->add(array('Access-Control-Allow-Origin' => '*'));

    $jsonp  = $request->query->get('jsonp');
    $format = $request->getRequestFormat();
    if ($format !== 'json') {
        $response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');
        return $response;
    }

    $deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($deck_id);
    if (!$deck) die();

    $owner = method_exists($deck, 'getUser') ? $deck->getUser()
           : (method_exists($deck, 'getOwner') ? $deck->getOwner() : null);
    if (!$owner || !$owner->getIsShareDecks()) die();

    if (method_exists($deck, 'getDateUpdate') && $deck->getDateUpdate()) {
        $response->setLastModified($deck->getDateUpdate());
        if ($response->isNotModified($request)) {
            return $response;
        }
    }

    $content = json_encode($deck);

    if (isset($jsonp)) {
        $content = "$jsonp($content)";
        $response->headers->set('Content-Type', 'application/javascript');
    } else {
        $response->headers->set('Content-Type', 'application/json');
    }

    $response->setContent($content);
    return $response;
	}
}
