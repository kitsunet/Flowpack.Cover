<?php
namespace Flowpack\Cover\Cache;

use TYPO3\Flow\Annotations as Flow;

/**
 * Wraps the Dispatcher and manipulates the Request and Response.
 *
 * @package Flowpack\Cover\Aspects
 * @Flow\Scope("singleton")
 */
class ResponseCacheHelper {

	/**
	 * @Flow\Inject(lazy=false)
	 * @var \TYPO3\Flow\Cache\Frontend\VariableFrontend
	 */
	protected $responseCache;

	/**
	 * @Flow\Inject(lazy=false)
	 * @var \TYPO3\Flow\Cache\Frontend\StringFrontend
	 */
	protected $contentCache;

	/**
	 * @Flow\Inject(setting="cacheSettings")
	 * @var array
	 */
	protected $settings;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Utility\Environment
	 */
	protected $environment;

	/**
	 * Will try to find a cache entry for the given request, if it is found the response is filled from cache and the request is set to dispatched.
	 * If a session is given and it is started then it will contribute to the cache identifier.
	 *
	 * @param \TYPO3\Flow\Mvc\RequestInterface $request
	 * @param \TYPO3\Flow\Mvc\ResponseInterface $response
	 * @param \TYPO3\Flow\Session\SessionInterface $session
	 */
	public function get(\TYPO3\Flow\Mvc\RequestInterface $request, \TYPO3\Flow\Mvc\ResponseInterface $response, \TYPO3\Flow\Session\SessionInterface $session = NULL) {
		$requestHash = $this->calculateRequestHash($request, $session);

		if ($this->responseCache->has($requestHash)) {
			$cachedResponse = $this->responseCache->get($requestHash);
			$response->setContent($this->contentCache->get($requestHash));
			$response->setHeaders($cachedResponse->getHeaders());
			$response->setStatus($cachedResponse->getStatusCode(), substr($cachedResponse->getStatus(), (strlen((string)$cachedResponse->getStatusCode()) + 1)));
			$response->setHeader('X-Cover-Cache', 'hit');
			$request->setDispatched(TRUE);
		} else {
			$response->setHeader('X-Cover-Cache', 'miss');
		}

		$request->getHttpRequest()->setHeader('X-Cover-CacheIdentifier', $requestHash);
	}

	/**
	 * @param \TYPO3\Flow\Mvc\RequestInterface $request
	 * @param \TYPO3\Flow\Session\SessionInterface $session
	 * @return boolean
	 */
	public function has(\TYPO3\Flow\Mvc\RequestInterface $request, \TYPO3\Flow\Session\SessionInterface $session = NULL) {
		$requestHash = md5(serialize($request));

		if ($session !== NULL && $session->isStarted()) {
			$requestHash .= md5($session->getId());
		}

		return $this->responseCache->has($requestHash);
	}

	/**
	 * @param \TYPO3\Flow\Mvc\RequestInterface $request
	 * @param \TYPO3\Flow\Mvc\ResponseInterface $response
	 * @param \TYPO3\Flow\Session\SessionInterface $session
	 *
	 * @return boolean
	 */
	public function set(\TYPO3\Flow\Mvc\RequestInterface $request, \TYPO3\Flow\Mvc\ResponseInterface $response, \TYPO3\Flow\Session\SessionInterface $session = NULL) {
		if (!$request->isDispatched()) {
			return FALSE;
		}

		$requestHash = $this->calculateRequestHash($request, $session, TRUE);

		$currentDate = new \DateTime();
		$response->setLastModified($currentDate);

		$cacheLifetime = $response->getHeaders()->getCacheControlDirective('max-age') !== NULL ? $response->getHeaders()->getCacheControlDirective('max-age') : $response->getHeaders()->getCacheControlDirective('s-maxage');
		if ($cacheLifetime === NULL) {
			$possibleExpiresDates = $response->getHeader('Expires');
			$possibleExpiresDate = is_array($possibleExpiresDates) ? array_pop($possibleExpiresDates) : NULL;
			if ($possibleExpiresDate !== NULL && $possibleExpiresDate instanceof \DateTime) {
				if ($possibleExpiresDate > $currentDate) {
					$timeDifference = $currentDate->diff($possibleExpiresDate);
					$cacheLifetime = $timeDifference->format('s');
				}
			}
		}

		if ($cacheLifetime === NULL) {
			$cacheLifetime = $this->settings['defaultLifetime'];
		}

		$responseContent = $response->getContent();
		$this->contentCache->set($requestHash, $responseContent, $this->calculateCacheTags($request, $session), $cacheLifetime);
		$response->setContent('');
		$this->responseCache->set($requestHash, $response, $this->calculateCacheTags($request, $session), $cacheLifetime);
		$response->setContent($responseContent);
		return TRUE;
	}

	/**
	 * @param \TYPO3\Flow\Mvc\RequestInterface $request
	 * @return boolean
	 */
	public function allowsCaching(\TYPO3\Flow\Mvc\RequestInterface $request) {
		if ($request->getHttpRequest()->getHeaders()->getCacheControlDirective('no-store') || $request->getHttpRequest()->getHeaders()->getCacheControlDirective('no-cache') || $request->getHttpRequest()->getHeaders()->getCacheControlDirective('private')) {
			return FALSE;
		}

		if ($request->getHttpRequest()->getHeaders()->getCacheControlDirective('max-age') !== NULL && $request->getHttpRequest()->getHeaders()->getCacheControlDirective('max-age') < 1) {
			return FALSE;
		}

		if ($request->getHttpRequest()->getHeaders()->getCacheControlDirective('s-maxage') !== NULL && $request->getHttpRequest()->getHeaders()->getCacheControlDirective('s-maxage') < 1) {
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * @param \TYPO3\Flow\Mvc\ResponseInterface $response
	 * @return boolean
	 */
	public function canBeCached(\TYPO3\Flow\Mvc\ResponseInterface $response) {
		if ($response->getHeaders()->getCacheControlDirective('no-store') || $response->getHeaders()->getCacheControlDirective('no-cache') || $response->getHeaders()->getCacheControlDirective('private')) {
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * @param \TYPO3\Flow\Mvc\RequestInterface $request
	 * @param \TYPO3\Flow\Session\SessionInterface $session
	 * @param boolean $useCacheIdentifierHeader
	 * @return string
	 */
	protected function calculateRequestHash(\TYPO3\Flow\Mvc\RequestInterface $request, \TYPO3\Flow\Session\SessionInterface $session = NULL, $useCacheIdentifierHeader = FALSE) {
		if ($useCacheIdentifierHeader && $request->getHttpRequest()->getHeader('X-Cover-CacheIdentifier')) {
			return $requestHash = $request->getHttpRequest()->getHeader('X-Cover-CacheIdentifier');
		} else {
			$dispatched = $request->isDispatched();
			$request->setDispatched(FALSE);
			$requestHash = serialize($request);
			$request->setDispatched($dispatched);
		}

		if ($session !== NULL && $session->isStarted()) {
			$requestHash .= '-' . $session->getId();
		}

		$requestHash .= '-' . (string)$this->environment->getContext();
		return md5($requestHash);
	}

	/**
	 * @param \TYPO3\Flow\Mvc\RequestInterface $request
	 * @param \TYPO3\Flow\Session\SessionInterface $session
	 * @return array
	 */
	protected function calculateCacheTags(\TYPO3\Flow\Mvc\RequestInterface $request, \TYPO3\Flow\Session\SessionInterface $session = NULL) {
		$cacheTags = array();
		$controllerObjectName = str_replace('\\', '_', $request->getControllerObjectName());
		$cacheTags[] = 'controllerObject%' . $controllerObjectName;
		$cacheTags[] = 'format%' . $request->getFormat();
		$cacheTags[] = 'action%' . $request->getControllerActionName();
		$cacheTags[] = 'controllerAction%' . $controllerObjectName . '-' . $request->getControllerActionName();

		if ($session !== NULL && $session->isStarted()) {
			$cacheTags[] = 'session-' . $session->getId();
		}

		return $cacheTags;
	}
}
