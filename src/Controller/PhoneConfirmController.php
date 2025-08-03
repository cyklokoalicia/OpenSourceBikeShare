<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\SmsSenderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Attribute\Route;

class PhoneConfirmController extends AbstractController
{
    #[Route('/user/confirm/phone/{key}', name: 'user_confirm_phone')]
    public function index(
        bool $isSmsSystemEnabled,
        SmsSenderInterface $smsSender,
        HistoryRepository $historyRepository,
        Request $request,
        SessionInterface $session,
        TranslatorInterface $translator,
        UserRepository $userRepository
    ): Response {
        // Only logged users can verify their phone
        $user = $this->getUser();
        if (!$isSmsSystemEnabled || $user->isNumberConfirmed()) {
            return $this->redirectToRoute('home');
        }

        $verificationStep = $session->get('phoneVerificationStep', 1);

        if ($verificationStep === 1) {
            // Step 1: Get phone number
            $form = $this->createFormBuilder()->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $number = $user->getNumber();

                // Generate SMS code
                $smsCode = chr(rand(65, 90)) . chr(rand(65, 90)) . ' ' . rand(100000, 999999);
                $sanitizedSmsCode = str_replace(' ', '', $smsCode);
                $checkCode = md5('WB' . $number . $sanitizedSmsCode);

                // Send SMS
                $text = $translator->trans('Enter this code to verify your phone: {smsCode}', ['smsCode' => $smsCode]);
                $smsSender->send($number, $text);
                $historyRepository->addItem(
                    $user->getUserId(),
                    0,
                    'PHONE_CONFIRM_REQUEST',
                    sprintf('%s;%s;%s', $number, $sanitizedSmsCode, $checkCode)
                );

                // Store for verification
                $session->set('phoneCheckCode', $checkCode);
                $session->set('phoneVerificationStep', 2);

                return $this->redirectToRoute('user_confirm_phone');
            }
        } else {
            // Step 2: Verify the code
            $form = $this->createFormBuilder()
                ->add('smscode', null, [
                    'label' => $translator->trans('SMS code (received to your phone):'),
                    'required' => true
                ])
                ->getForm();

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                $number = $user->getNumber();
                $checkCode = $session->get('phoneCheckCode');

                $parameter = $number . ';' . str_replace(' ', '', $data['smscode']) . ';' . $checkCode;
                $history = $historyRepository->findConfirmationRequest($parameter, $user->getUserId());

                if ($history) {
                    $userRepository->confirmUserNumber($user->getUserId());
                    $historyRepository->addItem($user->getUserId(), 0, 'PHONE_CONFIRMED', '');

                    $session->remove('phoneCheckCode');
                    $session->remove('phoneVerificationStep');

                    return $this->redirectToRoute('home');
                } else {
                    $this->addFlash('error', $translator->trans('Invalid confirmation code.'));
                }
            }
        }

        return $this->render(
            'phone.confirm.html.twig',
            [
                'form' => $form,
                'verificationStep' => $verificationStep
            ]
        );
    }
}
