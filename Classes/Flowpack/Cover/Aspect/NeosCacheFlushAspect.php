<?php
namespace Flowpack\Cover\Aspect;

/*                                                                        *
 * This script belongs to the Flowpack.Cover package.                     *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Eel\FlowQuery\FlowQuery;

/**
 * Wraps the Dispatcher and manipulates the Request and Response.
 *
 * @package Flowpack\Cover\Aspects
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class NeosCacheFlushAspect {

	/**
	 * @Flow\Inject(lazy=false)
	 * @var \TYPO3\Flow\Cache\Frontend\VariableFrontend
	 */
	protected $responseCache;

	/**
	 * @Flow\After("method(TYPO3\Neos\Service\PublishingService->publishNode())")
	 * @param JoinPointInterface $joinPoint The current join point
	 * @return void
	 */
	public function manipulateRequestAndResponse(JoinPointInterface $joinPoint) {
		$node = $joinPoint->getMethodArgument('node');
		$flowQuery = new FlowQuery(array($node));
		$documentNode = $flowQuery->closest('[instanceof TYPO3.Neos:Document]')->get(0);
		$this->responseCache->flushByTag('node-' . $documentNode->getIdentifier());
	}
}