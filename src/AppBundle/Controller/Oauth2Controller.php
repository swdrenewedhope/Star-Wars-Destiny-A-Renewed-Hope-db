<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Deck;
use Symfony\Component\HttpFoundation\JsonResponse;

class Oauth2Controller extends Controller
{
	public function listDecksAction(Request $request)
	{
		$response = new Response();
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));
		
		$decks = $this->getDoctrine()->getRepository('AppBundle:Deck')->findBy(['user' => $this->getUser()]);

		$dateUpdates = array_map(function ($deck) {
			return $deck->getDateUpdate();
		}, $decks);
		
		$response->setLastModified(max($dateUpdates));
		if ($response->isNotModified($request)) {
			return $response;
		}

		$content = json_encode($decks);
		
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}
	
	public function loadDeckAction($id)
	{
		$response = new Response();
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));
		
		$deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($id);

		if($deck->getUser()->getId() !== $this->getUser()->getId())
		{
			throw $this->createAccessDeniedException("Access denied to this object.");
		}
		
		$response->setLastModified($deck->getDateUpdate());
		if ($response->isNotModified($request)) {
			return $response;
		}

		$content = json_encode($deck);
		
		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}
	
	public function saveDeckAction($id, Request $request)
	{
		if(!$id)
		{
			$deck = new Deck();
			$this->getDoctrine()->getManager()->persist($deck);
		}
		else
		{
			$deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($id);
			if($deck->getUser()->getId() !== $this->getUser()->getId())
			{
				throw $this->createAccessDeniedException("Access denied to this object.");
			}
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
		
		$this->get('decks')->saveDeck($this->getUser(), $deck, $decklist_id, $name, $affiliation, $description, $tags, $slots, null);
		
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
