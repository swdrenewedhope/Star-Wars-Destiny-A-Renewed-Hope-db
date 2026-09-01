<?php

namespace AppBundle\Listener;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Security\Core\SecurityContext;

class UserCollectionListener
{
	private $twig;
	private $em = null;
	private $securityContext = null;

	public function __construct(\Twig_Environment $twig, EntityManager $entityManager, SecurityContext $securityContext)
	{
		$this->twig = $twig;
		$this->em = $entityManager;
		$this->securityContext = $securityContext;
	}

	public function onKernelController(FilterControllerEvent $event)
	{
		if($event->isMasterRequest())
		{
			if ($this->securityContext->getToken() && is_object($this->securityContext->getToken()->getUser()))
			{
   				$user = $this->securityContext->getToken()->getUser();
   				$collection = $this->em->getRepository('AppBundle:Collection')->getCollection($user->getId());
   				$this->twig->addGlobal('collection', $collection);
   			}
   		}
   	}
}