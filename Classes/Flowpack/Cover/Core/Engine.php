<?php
namespace Flowpack\Cover\Core;

use TYPO3\Eel\Context;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Utility\Arrays;

/**
 * The Core Engine is used to evaluate step configurations.
 *
 * @Flow\Scope("singleton")
 */
class Engine {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Eel\CompilingEvaluator
	 */
	protected $eelEvaluator;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Session\SessionInterface
	 */
	protected $session;

	/**
	 * @Flow\Inject
	 * @var \Flowpack\Cover\Cache\ResponseCacheHelper
	 */
	protected $responseCache;


	/**
	 * @param array $settings
	 */
	public function injectSettings($settings) {
		$this->settings = $settings;
	}

	/**
	 * @param string $stepName One of "beforeDispatch", "afterDispatch"
	 * @param object $request
	 * @param object $response
	 * @param array $additionalContextVariables
	 * @return boolean Did the step evaluate.
	 */
	public function evaluateStep($stepName, $request, $response, $additionalContextVariables = array()) {
		$contextVariables = array_merge(array(
			'request' => $request,
			'response' => $response,
			'session' => $this->session,
			'responseCache' => $this->responseCache
		), $additionalContextVariables);
		$context = new Context($contextVariables);

		$stepConfiguration = $this->settings['steps'];

		if (!isset($stepConfiguration[$stepName])) {
			return FALSE;
		}

		$stepSorter = new \TYPO3\Flow\Utility\PositionalArraySorter($stepConfiguration[$stepName], 'position');

		foreach ($stepSorter->toArray() as $actionConfigurationName => $possibleActionConfiguration) {
			if ($this->eelEvaluator->evaluate($possibleActionConfiguration['condition'], $context)) {
				$this->eelEvaluator->evaluate($possibleActionConfiguration['action'], $context);
			}
		}

		return TRUE;
	}
}