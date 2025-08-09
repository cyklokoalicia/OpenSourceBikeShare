<?php

namespace BikeShare\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LanguageController extends AbstractController
{
    public function switchLanguage(Request $request, string $locale): Response
    {
        $session = $request->getSession();
        $session->start();
        $session->set('_locale', $locale);
        $session->save();

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('home');
    }
}
