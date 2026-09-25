<?php
namespace AppBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends Controller
{
	public function findAction() { return $this->render('AppBundle:Admin:index.html.twig', []); }

	public function processAction(Request $request)
	{
    	$entityManager = $this->getDoctrine()->getManager();
		$user = $request->request->get('username') ? $entityManager->getRepository('AppBundle:User')->findOneBy(['username' => $request->request->get('username')]) : null;
		if(!$user) { $this->addFlash('danger', "Username does not exist."); return $this->redirect($this->generateUrl('admin')); }
		return $this->redirect($this->generateUrl('admin_show_user', [ 'user_id' => $user->getId() ]));
	}

	public function showAction($user_id)
	{
    	$entityManager = $this->getDoctrine()->getManager();
		$user = $entityManager->getRepository('AppBundle:User')->find($user_id);
		if(!$user) { throw $this->createNotFoundException("User not found"); }
		return $this->render('AppBundle:Admin:user_admin.html.twig', [ 'user' => $user, ]);
	}

	public function toggleDonationAction(Request $request, $user_id)
	{
    	$entityManager = $this->getDoctrine()->getManager();
    	$user = $entityManager->getRepository('AppBundle:User')->find($user_id);
    	$user->setDonation(!$user->getDonation());
    	$entityManager->flush();
    	$this->addFlash('success', sprintf( 'Donator status %s for user %s.', $user->getDonation() ? 'granted' : 'revoked', $user->getUsername()));
		return $this->redirect($this->generateUrl('admin_show_user', [ 'user_id' => $user->getId() ]));
	}

	public function decklistsAction($user_id)
	{
    	$entityManager = $this->getDoctrine()->getManager();
		$user = $entityManager->getRepository('AppBundle:User')->find($user_id);
		return $this->render('AppBundle:Admin:user_decklists.html.twig', [ 'user' => $user, ]);
	}
	
	public function deleteDecklistAction($decklist_id)
	{
    	$entityManager = $this->getDoctrine()->getManager();
		$decklist = $entityManager->getRepository('AppBundle:Decklist')->find($decklist_id);
		$successors = $entityManager->getRepository('AppBundle:Decklist')->findBy(array('precedent' => $decklist));
		foreach($successors as $successor) { $successor->setPrecedent(null); }	
		$children = $entityManager->getRepository('AppBundle:Deck')->findBy(array('parent' => $decklist));
		foreach($children as $child) {$child->setParent(null);}
		$entityManager->flush();		
		$entityManager->remove($decklist);
		$entityManager->flush();
		return $this->redirect($this->generateUrl('admin_user_decklists_show', [ 'user_id' => $decklist->getUser()->getId() ]));
	}

	public function commentsAction($user_id)
	{
		$user = $this->getDoctrine()->getManager()->getRepository('AppBundle:User')->find($user_id);
		if(!$user) { throw $this->createNotFoundException("User not found"); }
		return $this->render('AppBundle:Admin:user_comments.html.twig', [ 'user' => $user, ]);
	}

	public function deleteCommentAction($comment_id)
	{
    	$entityManager = $this->getDoctrine()->getManager();
		$comment = $entityManager->getRepository('AppBundle:Comment')->find($comment_id);
		if(!$comment) { throw $this->createNotFoundException("Comment not found"); }
		$entityManager->remove($comment);
		$entityManager->flush();
		return $this->redirect($this->generateUrl('admin_user_comments_show', [ 'user_id' => $comment->getUser()->getId() ]));
	}
}