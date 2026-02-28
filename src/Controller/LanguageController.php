<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Entity\User;
use BikeShare\Repository\UserSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class LanguageController extends AbstractController
{
    public function __construct(
        private readonly array $enabledLocales,
        private readonly TranslatorInterface $translator,
        private readonly UserSettingsRepository $userSettingsRepository,
    ) {
    }

    public function switchLanguage(Request $request, string $locale): Response
    {
        if (in_array($locale, $this->enabledLocales, true)) {
            $request->getSession()->set('_locale', $locale);

            /** @var User $user */
            $user = $this->getUser();
            if ($user) {
                $this->userSettingsRepository->saveLocale($user->getUserId(), $locale);
            }
        }

        $referer = $request->headers->get('referer');
        if ($referer && !str_contains($referer, '/switchLanguage')) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('home');
    }

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
            'Invalid code format. Use four digits.',
        ];

        $translations = [];
        foreach ($keys as $key) {
            $translations[$key] = $this->translator->trans($key);
        }

        return new JsonResponse($translations);
    }
}
