<?php

namespace BikeShare\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LanguageController extends AbstractController
{
    public function __construct(private readonly array $enabledLocales)
    {
    }

    public function switchLanguage(Request $request, string $locale): Response
    {
        if (in_array($locale, $this->enabledLocales, true)) {
            $request->getSession()->set('_locale', $locale);
        }

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('home');
    }
}
