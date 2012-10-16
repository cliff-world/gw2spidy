<?php
use GW2Spidy\Security\CustomAuthenticationSuccessHandler;
use GW2Spidy\Security\CustomLogoutSuccessHandler;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bridge\Twig\Extension\SecurityExtension;

use GW2Spidy\DB\User;

error_reporting(E_ALL);
ini_set('display_errors', 'On');
/**
 * using Silex micro framework
 *  this file contains all routing and the 'controllers' using lambda functions
 */

use GW2Spidy\Util\Functions;

use GW2Spidy\Application;

use GW2Spidy\UserProvider;

use GW2Spidy\Twig\VersionedAssetsRoutingExtension;
use GW2Spidy\Twig\ItemListRoutingExtension;
use GW2Spidy\Twig\GW2MoneyExtension;
use GW2Spidy\Twig\GenericHelpersExtension;

require dirname(__FILE__) . '/../autoload.php';

// initiate the application, check config to enable debug / sql logging when needed
$app = Application::getInstance();

// register config provider
$app->register(new Igorw\Silex\ConfigServiceProvider(getAppConfig()));

// setup dev mode related stuff based on config
$app['sql_logging'] && $app->enableSQLLogging();

// register security provider
$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'login' => array(
            'pattern' => '^/login$',
        ),
        'main' => array(
            'anonymous' => true,
            'form'      => array('login_path' => '/login', 'check_path' => '/login_check'),
            'logout'    => array('logout_path' => '/logout'),
            'users'     => $app->share(function () use ($app) {
                return new UserProvider();
            }),
            /*'users'     => array(
             // raw password is foo
                    'admin' => array('ROLE_ADMIN', '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
                    'user'  => array('ROLE_USER',  '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
            ),*/
        ),
    ),
    'security.role_hierarchy' => array(
        'ROLE_ADMIN' => array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
        'ROLE_USER' => array(),
    ),
    'security.access_rules' => array(
        array('^/admin', 'ROLE_ADMIN'),
    ),
));

$app['security.authentication.success_handler._proto'] = $app->protect(function ($name, $options) use ($app) {
    return $app->share(function () use ($name, $options, $app) {
        $handler = new CustomAuthenticationSuccessHandler(
                $app['security.http_utils'],
                $options
        );
        $handler->setProviderKey($name);

        return $handler;
    });
});

$app['security.authentication.logout_handler._proto'] = $app->protect(function ($name, $options) use ($app) {
    return $app->share(function () use ($name, $options, $app) {
        return new CustomLogoutSuccessHandler(
            $app['security.http_utils'],
            isset($options['target_url']) ? $options['target_url'] : '/'
        );
    });
});

// hit the security.firewall_map and the security so they initialize properly before Twig tries to use them in some odd way
$app['security.firewall_map'];
$app['security'];

$app['user'] = $app->share(function() use ($app) {
    if (!$app['security']->getToken() || !($user = $app['security']->getToken()->getUser()) || !$user instanceof User) {
        return null;
    }

    return $user;
});
// register providers
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path'    => dirname(__FILE__) . '/../templates',
    'twig.options' => array(
        'cache' => dirname(__FILE__) . '/../tmp/twig-cache',
    ),
));

// register custom twig extensions
$app['twig']->addExtension(new GenericHelpersExtension());
$app['twig']->addExtension(new VersionedAssetsRoutingExtension());
$app['twig']->addExtension(new GW2MoneyExtension());
$app['twig']->addExtension(new ItemListRoutingExtension($app['url_generator']));

$app->before(function(Request $request) use ($app) {
    $app['isLoggedIn'] = (boolean)$request->cookies->get('logged_in', null);

    if (!$request->isXmlHttpRequest() && $request->getMethod() == 'GET' && !preg_match("/^\/login/", $request->getRequestUri())) {
        $app['session']->set('_security.main.target_path', $request->getRequestUri());
    }
});

/*
 * it's not very clean and silex-like but following are some includes to split up all the routing / functionality
 *  instead of using their mounting stuff, because it's just to much trouble
 */
$root = dirname(__DIR__);

// helper functions shared among the various others
require "{$root}/controllers/helpers.php";

// generic stuff like the homepage, etc
require "{$root}/controllers/other.php";

// type list, item lists, item page and chart
require "{$root}/controllers/items.php";

// recipe lists and recipe
require "{$root}/controllers/crafting.php";

// gem and chart
require "{$root}/controllers/gems.php";

// search
require "{$root}/controllers/search.php";

// watchlist
require "{$root}/controllers/watchlist.php";

// api stuff
require "{$root}/controllers/api.php";

// login stuff
require "{$root}/controllers/security.php";

// bootstrap the app
$app->run();
