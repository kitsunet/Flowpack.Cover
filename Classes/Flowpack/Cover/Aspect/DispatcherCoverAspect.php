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
use TYPO3\Flow\Mvc\ActionRequest;

/**
 * Wraps the Dispatcher and manipulates the Request and Response.
 *
 * @package Flowpack\Cover\Aspects
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class DispatcherCoverAspect {

	/**
	 * @Flow\Inject
	 * @var \Flowpack\Cover\Core\Engine
	 */
	protected $coreEngine;

	/**
	 * @Flow\Around("method(TYPO3\Flow\Mvc\Dispatcher->dispatch())")
	 * @param JoinPointInterface $joinPoint The current join point
	 * @return mixed The result of the dispatch method (NULL)
	 *
	 * @throws \Exception Any Exception the dispatch method threw will be thrown at the end.
	 */
	public function manipulateRequestAndResponse(JoinPointInterface $joinPoint) {
		$dispatcherException = NULL;
		$dispatcher = $joinPoint->getProxy();
		$arguments = $joinPoint->getMethodArguments();
		/**
		 * @var $request ActionRequest
		 */
		$request = $arguments['request'];

		// We use some methods not defined in the Interfaces so we can only work with ActionRequests
		if (!($request instanceof ActionRequest)) {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}

		/**
		 * @var $response \TYPO3\Flow\Mvc\ResponseInterface
		 */
		$response = $arguments['response'];

		$this->coreEngine->evaluateStep('beforeDispatch', $request, $response);
		if ($request->isDispatched()) {
			return;
		}

		try {
			$result = $joinPoint->getAdviceChain()->proceed($joinPoint);
		} catch (\Exception $dispatcherException) {}

		$this->coreEngine->evaluateStep('afterDispatch', $arguments['request'], $arguments['response']);

		if ($dispatcherException !== NULL) {
			throw $dispatcherException;
		}

		return $result;
	}


}