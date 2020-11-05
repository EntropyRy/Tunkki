<?php
use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Request\RequestFactory;
require dirname(__DIR__).'/config/bootstrap.php';
if ($_SERVER['APP_DEBUG'] && in_array(@$_SERVER['REMOTE_ADDR'], [$_ENV['TRUSTED_IPS']]) ) {
    umask(0000);
    Debug::enable();
} else {
    $_SERVER['APP_ENV'] = 'prod';
}
if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}
if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
//$request = Request::createFromGlobals();
$request = RequestFactory::createFromGlobals('host_with_path_by_locale');
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
