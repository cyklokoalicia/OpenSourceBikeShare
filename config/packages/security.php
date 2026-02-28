<?php

declare(strict_types=1);

use BikeShare\App\Security\ApiAccessDeniedHandler;
use BikeShare\App\Security\ApiV1Authenticator;
use BikeShare\App\Security\ApiServiceUserProvider;
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
        ->algorithm('auto')
        ->migrateFrom(['legacy_sha512']);

    $security
        ->passwordHasher('legacy_sha512')
        ->algorithm('sha512')
        ->encodeAsBase64(false)
        ->iterations(1);

    $security
        ->provider('app_user_provider')
        ->id(UserProvider::class);

    $security
        ->provider('api_service_user_provider')
        ->id(ApiServiceUserProvider::class);

    $security
        ->firewall('dev')
        ->pattern('^/(_(profiler|wdt)|css|images|js)/')
        ->security(false);

    $apiFirewall = $security->firewall('api_v1');
    $apiFirewall
        ->provider('app_user_provider')
        ->security(true)
        ->pattern('^/api/v1')
        ->context('main')
        ->accessDeniedHandler(ApiAccessDeniedHandler::class)
        ->entryPoint(ApiV1Authenticator::class);
    $apiFirewall
        ->customAuthenticators([ApiV1Authenticator::class]);

    $mainFirewall = $security->firewall('main');
    $mainFirewall
        ->provider('app_user_provider')
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
        ->roles(['ROLE_ADMIN']);

    $security->accessControl()
        ->path('^/admin/qrCodeGenerator')
        ->roles(['ROLE_SUPER_ADMIN']);

    $security
        ->accessControl()
        ->path('^/api/v1/auth/(token|refresh|logout)$')
        ->roles(['PUBLIC_ACCESS']);
    $security
        ->accessControl()
        ->path('^/api/v1/admin')
        ->roles(['ROLE_ADMIN']);
    $security
        ->accessControl()
        ->path('^/api/v1/stands/markers$')
        ->roles(['ROLE_USER', 'ROLE_API']);
    $security
        ->accessControl()
        ->path('^/api/v1')
        ->roles(['ROLE_USER']);
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
        ->path('^/js/translations.json')
        ->roles(['PUBLIC_ACCESS']);
    $security
        ->accessControl()
        ->path('^/switchLanguage')
        ->roles(['PUBLIC_ACCESS']);
    $security
        ->accessControl()
        ->path('^/')
        ->roles(['ROLE_USER']);
};
