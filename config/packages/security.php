<?php

declare(strict_types=1);

use BikeShare\App\Security\ApiAccessDeniedHandler;
use BikeShare\App\Security\ApiTokenAuthenticator;
use BikeShare\App\Security\TokenProvider;
use BikeShare\App\Security\UserConfirmedEmailChecker;
use BikeShare\App\Security\UserProvider;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Config\SecurityConfig;

return function (SecurityConfig $security) {

    $security->enableAuthenticatorManager(true);

    $security->roleHierarchy('ROLE_ADMIN', ['ROLE_USER']);
    $security->roleHierarchy('ROLE_SUPER_ADMIN', ['ROLE_ADMIN']);

    $security
        ->passwordHasher(PasswordAuthenticatedUserInterface::class)
        ->algorithm('sha512')
        ->encodeAsBase64(false)
        ->iterations(1);

    $security
        ->provider('app_user_provider')
        ->id(UserProvider::class);

    $security
        ->firewall('dev')
        ->pattern('^/(_(profiler|wdt)|css|images|js)/')
        ->security(false);

    $apiFirewall = $security->firewall('api');
    $apiFirewall
        ->security(true)
        ->pattern('^/api')
        ->context('main')
        ->accessDeniedHandler(ApiAccessDeniedHandler::class);
    $apiFirewall
        ->customAuthenticators([ApiTokenAuthenticator::class]);
    $apiFirewall
        ->httpBasic()
        ->realm('Bike Sharing API');
    $security
        ->accessControl()
        ->path('^/api')
        ->roles(['ROLE_USER', 'ROLE_API']);

    $mainFirewall = $security->firewall('main');
    $mainFirewall
        ->formLogin()
        ->usernameParameter('number')
        ->passwordParameter('password')
        ->loginPath('login')
        ->checkPath('login');
    $mainFirewall
        ->logout()
        ->path('logout')
        ->target('/');
    $mainFirewall
        ->rememberMe()
        ->secret('%kernel.secret%')
        ->lifetime(604800) // 1 week in seconds
        ->tokenProvider(
            [
                'service' => TokenProvider::class,
            ]
        );
    $mainFirewall
        ->pattern('^/')
        ->userChecker(UserConfirmedEmailChecker::class);

    $security->accessControl()
        ->path('^/admin')
        ->roles(['ROLE_SUPER_ADMIN']);

    $security->accessControl()
        ->path('^/admin/qrCodeGenerator')
        ->roles(['ROLE_SUPER_ADMIN']);

    $security
        ->accessControl()
        ->path('^/login$')
        ->roles(['PUBLIC_ACCESS']);
    $security
        ->accessControl()
        ->path('^/(sms/)?receive.php$')
        ->roles(['PUBLIC_ACCESS']);
    $security
        ->accessControl()
        ->path('^/register(.php)?$')
        ->roles(['PUBLIC_ACCESS']);
    $security
        ->accessControl()
        ->path('^/user/confirm/email(/\w*)?$')
        ->roles(['PUBLIC_ACCESS']);
    $security
        ->accessControl()
        ->path('^/resetPassword$')
        ->roles(['PUBLIC_ACCESS']);
    $security
        ->accessControl()
        ->path('^/command.php$')
        ->roles(['PUBLIC_ACCESS']);

    $security
        ->accessControl()
        ->path('^/')
        ->roles(['ROLE_USER']);
};
