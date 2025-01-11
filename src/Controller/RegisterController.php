<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Form\PhoneValidationFormType;
use BikeShare\Form\RegistrationFormType;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\User\UserRegistration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterController extends AbstractController
{
    /**
     * @Route("/register", name="register")
     * @Route("/register.php", name="register")
     */
    public function index(
        bool $isSmsSystemEnabled,
        SmsSenderInterface $smsSender,
        HistoryRepository $historyRepository,
        Request $request,
        SessionInterface $session,
        TranslatorInterface $translator,
        UserRegistration $userRegistration
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $registrationStep = $session->get('registrationStep', $isSmsSystemEnabled ? 1 : 2);

        if ($registrationStep === 1) {
            $form = $this->createForm(PhoneValidationFormType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                $number = $data['number'];

                $smsCode = chr(rand(65, 90)) . chr(rand(65, 90)) . ' ' . rand(100000, 999999);
                $sanitizedSmsCode = str_replace(' ', '', $smsCode);
                $checkCode = md5('WB' . $number . $sanitizedSmsCode);

                $text = $translator->trans('Enter this code to register: {smsCode}', ['smsCode' => $smsCode]);
                //information to send table will be written even if sms system is disabled
                $smsSender->send($number, $text);
                $historyRepository->addItem(0, 0, 'REGISTER', "$number;$sanitizedSmsCode;$checkCode");

                $session->set('validatedNumber', $number);
                $session->set('checkCode', $checkCode);
                $session->set('registrationStep', 2);

                $this->addFlash('success', $translator->trans('SMS code has been sent to your phone.'));

                return $this->redirectToRoute('register');
            }
        } else {
            $form = $this->createForm(RegistrationFormType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();

                //TODO registration when there is no SMS system enabled
                $user = $userRegistration->register(
                    $session->get('validatedNumber'),
                    $data['useremail'],
                    substr(md5(mt_rand() . microtime() . $data['fullname']), 0, 8),
                    $data['city'],
                    $data['fullname'],
                    0
                );

                /**
                 * @phpcs:disable Generic.Files.LineLength
                 */
                $this->addFlash(
                    'success',
                    $translator->trans(
                        'You have been successfully registered. Please, check your email and read the instructions to finish your registration.'
                    )
                );

                return $this->redirectToRoute('home');
            }
        }

        return $this->render('register.html.twig', [
            'form' => $form->createView(),
            'registrationStep' => $registrationStep,
            'isSmsSystemEnabled' => $isSmsSystemEnabled,
        ]);
    }
}
