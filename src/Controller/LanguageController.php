<?php

namespace BikeShare\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class LanguageController extends AbstractController
{
    public function __construct(
        private readonly array $enabledLocales,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: '/switchLanguage/{_locale}',
        name: 'switch_language',
        requirements: ['_locale' => '[a-z]{2}'],
        defaults: ['_locale' => 'en']
    )]
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

    #[Route(
        path: '/js/translations.json',
        name: 'js_translations',
        requirements: ['_locale' => '[a-z]{2}'],
        defaults: ['_locale' => 'en']
    )]
    public function getTranslations(): JsonResponse
    {
        $keys = [
            'Select stand',
            'bicycle',
            'bicycles',
            'No bicycles',
            'Open a map with directions to the selected stand from your current location.',
            'walking directions',
            'Display photo of the stand.',
            'photo',
            'You have this bicycle currently rented. The current lock code is displayed below the bike number.',
            'Reported problem on this bicycle:',
            'sec.',
            'min.',
            'hour/s',
            'left',
            'over',
        ];

        $translations = [];
        foreach ($keys as $key) {
            $translations[$key] = $this->translator->trans($key);
        }

        return new JsonResponse($translations);
    }
}
