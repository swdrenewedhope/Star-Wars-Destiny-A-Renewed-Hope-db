<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends Controller
{
	public function findAction()
	{
		return $this->render('AppBundle:Admin:index.html.twig', [
				'pagetitle' => "Admin"
		]);
	}

	public function processAction(Request $request)
	{
		$parameters = $request->request->all();
		$entityManager = $this->getDoctrine()->getEntityManager();
		$user = null;

		if($request->request->get('username')) {
			$user = $entityManager->getRepository('AppBundle:User')->findOneBy(['username' => $request->request->get('username')]);
		}

		if(!$user) {
			$this->addFlash('danger', "Username does not exist.");
			return $this->redirect($this->generateUrl('admin'));
		}
		return $this->redirect($this->generateUrl('admin_show_user', [ 'user_id' => $user->getId() ]));
	}

	public function showAction($user_id)
	{
		$entityManager = $this->getDoctrine()->getEntityManager();
		$user = $entityManager->getRepository('AppBundle:User')->find($user_id);
		if(!$user) {
			throw $this->createNotFoundException("User not found");
		}

		return $this->render('AppBundle:Admin:user_admin.html.twig', [
				'pagetitle' => "User Admin",
				'user' => $user,
		]);
	}
	
	public function toggleEnabledAction($user_id)
	{
		$entityManager = $this->getDoctrine()->getEntityManager();
		$user = $entityManager->getRepository('AppBundle:User')->find($user_id);	
		if ($user->getId() !== $this->getUser()->getId()) { $user->setEnabled(!$user->isEnabled()); }
		$entityManager->flush();

		$this->addFlash('success', sprintf('Account of %s has been %s.', $user->getUsername(), $user->isEnabled() ? 'enabled' : 'disabled'));
		return $this->redirect($this->generateUrl('admin_show_user', [ 'user_id' => $user->getId() ]));
	}

	public function toggleDonationAction(Request $request, $user_id)
	{
    	$em = $this->getDoctrine()->getManager();
    	$user = $em->getRepository('AppBundle:User')->find($user_id);
    	$user->setDonation(!$user->getDonation());
    	$em->flush();

    	$this->addFlash('success', sprintf( 'Donator status %s for user %s.', $user->getDonation() ? 'granted' : 'revoked', $user->getUsername()));
		return $this->redirect($this->generateUrl('admin_show_user', [ 'user_id' => $user->getId() ]));
	}

	public function decklistsAction($user_id)
	{
		$entityManager = $this->getDoctrine()->getEntityManager();
		$user = $entityManager->getRepository('AppBundle:User')->find($user_id);
	
		return $this->render('AppBundle:Admin:user_decklists.html.twig', [
				'pagetitle' => "User Admin",
				'user' => $user,
		]);
	}
	
	public function deleteDecklistAction($decklist_id)
	{
		$entityManager = $this->getDoctrine()->getEntityManager();
		$decklist = $entityManager->getRepository('AppBundle:Decklist')->find($decklist_id);
		
		if(!$decklist) {
			throw $this->createNotFoundException("Decklist not found");
		}
			
		$successors = $entityManager->getRepository('AppBundle:Decklist')->findBy(array(
				'precedent' => $decklist
		));
		foreach($successors as $successor) {
			$successor->setPrecedent(null);
		}
		
		$children = $entityManager->getRepository('AppBundle:Deck')->findBy(array(
				'parent' => $decklist
		));
		foreach($children as $child) {
			$child->setParent(null);
		}
		
		$entityManager->flush();		
		$entityManager->remove($decklist);
		$entityManager->flush();
	
		return $this->redirect($this->generateUrl('admin_user_decklists_show', [ 'user_id' => $decklist->getUser()->getId() ]));
	}

	public function commentsAction($user_id)
	{
		$entityManager = $this->getDoctrine()->getEntityManager();
		$user = $entityManager->getRepository('AppBundle:User')->find($user_id);
		if(!$user) {
			throw $this->createNotFoundException("User not found");
		}
	
		return $this->render('AppBundle:Admin:user_comments.html.twig', [
				'pagetitle' => "User Admin",
				'user' => $user,
		]);
	}
	
	public function toggleHiddenCommentAction($comment_id)
	{
		$entityManager = $this->getDoctrine()->getEntityManager();
		$comment = $entityManager->getRepository('AppBundle:Comment')->find($comment_id);

		if(!$comment) { throw $this->createNotFoundException("Comment not found"); }
		
		$comment->setIsHidden(!$comment->getIsHidden());
		$entityManager->flush();
		
		return $this->redirect($this->generateUrl('admin_user_comments_show', [ 'user_id' => $comment->getUser()->getId() ]));
	}

	public function deleteCommentAction($comment_id)
	{
		$entityManager = $this->getDoctrine()->getEntityManager();
		$comment = $entityManager->getRepository('AppBundle:Comment')->find($comment_id);
		
		if(!$comment) { throw $this->createNotFoundException("Comment not found"); }
	
		$entityManager->remove($comment);
		$entityManager->flush();
	
		return $this->redirect($this->generateUrl('admin_user_comments_show', [ 'user_id' => $comment->getUser()->getId() ]));
	}
}
