<?php
declare(strict_types=1);

namespace Flownative\Canto\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Utility\Algorithms;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Aspect
 */
class DebugTimerAspect
{
    /**
     * @Flow\Inject(name="Flownative.Canto:TimingLogger")
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        $GLOBALS['canto-timer-request-id'] = $GLOBALS['canto-timer-request-id'] ?? Algorithms::generateRandomToken(12);
    }

    /**
     * @Flow\Around("method(public Flownative\Canto\.*->.*())")
     */
    public function logPublicTiming(JoinPointInterface $joinPoint)
    {
        return $this->logTiming($joinPoint);
    }

    /**
     * @Flow\Around("method(protected Flownative\Canto\.*->.*())")
     */
    public function logProtectedTiming(JoinPointInterface $joinPoint)
    {
        return $this->logTiming($joinPoint);
    }

    private function logTiming(JoinPointInterface $joinPoint)
    {
        $requestId = $GLOBALS['canto-timer-request-id'];
        $methodName = $joinPoint->getClassName() . '::' . $joinPoint->getMethodName();
        $requestStart = $_SERVER["REQUEST_TIME_FLOAT"];
        $start = microtime(true);
        $this->logger->debug(sprintf('%s,%s,%s,', $requestId, $methodName, $start - $requestStart));

        $result = $joinPoint->getAdviceChain()->proceed($joinPoint);

        $end = microtime(true);
        $elapsed = $end - $start;
        $this->logger->debug(sprintf('%s,%s,%s,%s', $requestId, $methodName, $end - $requestStart, $elapsed));

        return $result;
    }
}
