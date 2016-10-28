<?php

namespace PHPCD;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Lvht\MsgpackRpc\JsonMessenger;
use Lvht\MsgpackRpc\Io;
use Lvht\MsgpackRpc\MsgpackMessenger;

/**
 * Simple factory to separate details of object creation
 */
class Factory
{
    /**
     * @return ContainerBuilder
     */
    public function createDIContainer($configFileName, $configDir, $additionalParameters = [])
    {
        $container = new ContainerBuilder;

        $loader = new YamlFileLoader($container, new FileLocator($configDir));

        $loader->load($configFileName);

        foreach ($additionalParameters as $name => $value) {
            $container->setParameter($name, $value);
        }

        $container->compile();

        return $container;
    }

    /**
     * @param string $implementation absolute namespace path to a concrete logger
     * @param array $parameters parameters to logger's constructor
     * @return \Psr\Log\LoggerInterface
     */
    public function createLogger($implementation, $parameters = [])
    {
        switch ($implementation) {
            case '\\PHPCD\\Log\\DateTimeLogger':
                $decoratedLogger = $this->createLogger('\\PHPCD\\Log\\Logger', $parameters);
                return new Log\DateTimeLogger($decoratedLogger);
            break;
            case '\\PHPCD\\Log\\NullLogger':
                return new Log\NullLogger;
            break;
            case '\\Monolog\\Logger':
                $path = ((isset($parameters[0]) && is_string($parameters[0])) ? $parameters[0] : getenv('HOME') . '/.phpcd.log');

                $logger = new \Monolog\Logger('PHPCD');
                $logger->pushHandler(new \Monolog\Handler\StreamHandler($path, \Monolog\Logger::DEBUG));
                return $logger;
            break;
            case '\\PHPCD\\Log\\Logger':
            default:
                $path = null;

                if (isset($parameters[0])) {
                    $path = $parameters[0];
                }

                return new Log\Logger($path);
            break;
        }
    }

    /**
     * @return \PHPCD\PatternMatcher\PatternMatcher
     */
    public function createPatternMatcher($match_type = 'head', $case_sensitivity = null)
    {
        $case_sensitivity = (bool)$case_sensitivity;

        if ($match_type === 'subsequence') {
            return new \PHPCD\PatternMatcher\SubsequencePatternMatcher($case_sensitivity);
        }

        return new \PHPCD\PatternMatcher\HeadPatternMatcher($case_sensitivity);
    }

    public function createMessenger(IO $io, $messengerType = null)
    {
        if ($messengerType === 'json') {
            return new JsonMessenger($io);
        } else {
            return new MsgpackMessenger($io);
        }
    }
}
