<?php
namespace Fab\VidiFrontend\Persistence;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Fab\Vidi\Domain\Model\Selection;
use Fab\Vidi\Resolver\FieldPathResolver;
use Fab\VidiFrontend\Tca\FrontendTca;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use Fab\Vidi\Persistence\Matcher;
use Fab\Vidi\Tca\Tca;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * Factory class related to Matcher object.
 */
class MatcherFactory implements SingletonInterface
{

    /**
     * @var array
     */
    protected $settings = array();

    /**
     * Constructor
     *
     * @param array $settings
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Gets a singleton instance of this class.
     *
     * @param array $settings
     * @return MatcherFactory
     */
    static public function getInstance(array $settings)
    {
        return GeneralUtility::makeInstance(MatcherFactory::class, $settings);
    }

    /**
     * Returns a matcher object.
     *
     * @param array $matches
     * @param string $dataType
     * @return Matcher
     */
    public function getMatcher($matches = array(), $dataType)
    {

        /** @var $matcher Matcher */
        $matcher = GeneralUtility::makeInstance(\Fab\Vidi\Persistence\Matcher::class, $matches, $dataType);

        $matcher = $this->applyCriteriaFromDataTables($matcher, $dataType);
        $matcher = $this->applyCriteriaFromSelection($matcher, $dataType);
        $matcher = $this->applyCriteriaFromAdditionalConstraints($matcher); // ? @todo really useful?? could be given as example

        // @todo Additional constraints = , like, >, >= , < , <=

        // Trigger signal for post processing Matcher Object.
        $this->emitPostProcessMatcherObjectSignal($matcher);

        return $matcher;
    }

    /**
     * Apply criteria from categories.
     *
     * @param Matcher $matcher
     * @return Matcher $matcher
     */
    protected function applyCriteriaFromAdditionalConstraints(Matcher $matcher)
    {

        if (!empty($this->settings['additionalEquals'])) {
            $constraints = GeneralUtility::trimExplode(',', $this->settings['additionalEquals'], TRUE);
            foreach ($constraints as $constraint) {

                if (preg_match('/.+=.+/isU', $constraint, $matches)) {
                    $constraintParts = GeneralUtility::trimExplode('=', $constraint, TRUE);
                    if (count($constraintParts) === 2) {
                        $matcher->equals(trim($constraintParts[0]), trim($constraintParts[1]));
                    }
                } elseif (preg_match('/.+like.+/isU', $constraint, $matches)) {
                    $constraintParts = GeneralUtility::trimExplode('like', $constraint, TRUE);
                    if (count($constraintParts) === 2) {
                        $matcher->like(trim($constraintParts[0]), trim($constraintParts[1]));
                    }
                }

            }
        }
        return $matcher;
    }

    /**
     * Apply criteria specific to jQuery plugin DataTable.
     *
     * @param Matcher $matcher
     * @param string $dataType
     * @return Matcher $matcher
     */
    protected function applyCriteriaFromDataTables(Matcher $matcher, $dataType)
    {
        // Special case for Grid in the BE using jQuery DataTables plugin.
        // Retrieve a possible search term from GP.
        $query = GeneralUtility::_GP('search');
        if (is_array($query)) {
            if (!empty($query['value'])) {
                $query = $query['value'];
            } else {
                $query = '';
            }
        }

        if (strlen($query) > 0) {

            // Parse the json query coming from the Visual Search.
            $query = rawurldecode($query);
            $queryParts = json_decode($query, TRUE);

            if (is_array($queryParts)) {
                $matcher = $this->parseQuery($queryParts, $matcher, $dataType);
            } else {
                $matcher->setSearchTerm($query);
            }
        }
        return $matcher;
    }

    /**
     * Apply criteria from selection.
     *
     * @param Matcher $matcher
     * @param string $dataType
     * @return Matcher $matcher
     */
    protected function applyCriteriaFromSelection(Matcher $matcher, $dataType)
    {

        $selectionIdentifier = (int)$this->settings['selection'];
        if ($selectionIdentifier > 0) {

            /** @var \Fab\Vidi\Domain\Repository\SelectionRepository $selectionRepository */
            $selectionRepository = $this->getObjectManager()->get('Fab\Vidi\Domain\Repository\SelectionRepository');

            /** @var Selection $selection */
            $selection = $selectionRepository->findByUid($selectionIdentifier);
            $queryParts = json_decode($selection->getQuery(), TRUE);
            $matcher = $this->parseQuery($queryParts, $matcher, $dataType);
        }
        return $matcher;
    }

    /**
     * Apply criteria specific to jQuery plugin DataTable.
     *
     * @param array $queryParts
     * @param Matcher $matcher
     * @param string $dataType
     * @return Matcher $matcher
     */
    protected function parseQuery(array $queryParts, Matcher $matcher, $dataType)
    {

        foreach ($queryParts as $queryPart) {
            $fieldNameAndPath = key($queryPart);

            $resolvedDataType = $this->getFieldPathResolver()->getDataType($fieldNameAndPath, $dataType);
            $fieldName = $this->getFieldPathResolver()->stripFieldPath($fieldNameAndPath, $dataType);

            // Retrieve the value.
            $value = current($queryPart);

            if (FrontendTca::grid($resolvedDataType)->hasFacet($fieldName) && FrontendTca::grid($resolvedDataType)->facet($fieldName)->canModifyMatcher()) {
                $matcher = FrontendTca::grid($resolvedDataType)->facet($fieldName)->modifyMatcher($matcher, $value);
            } elseif (Tca::table($resolvedDataType)->hasField($fieldName)) {
                // Check whether the field exists and set it as "equal" or "like".
                if ($this->isOperatorEquals($fieldNameAndPath, $dataType, $value)) {
                    $matcher->equals($fieldNameAndPath, $value);
                } else {
                    $matcher->like($fieldNameAndPath, $value);
                }
            } elseif ($fieldNameAndPath === 'text') {
                // Special case if field is "text" which is a pseudo field in this case.
                // Set the search term which means Vidi will
                // search in various fields with operator "like". The fields come from key "searchFields" in the TCA.
                $matcher->setSearchTerm($value);
            }
        }

        return $matcher;
    }

    /**
     * Tell whether the operator should be equals instead of like for a search, e.g. if the value is numerical.
     *
     * @param string $fieldName
     * @param string $dataType
     * @param string $value
     * @return bool
     */
    protected function isOperatorEquals($fieldName, $dataType, $value)
    {
        return (Tca::table($dataType)->field($fieldName)->hasRelation() && MathUtility::canBeInterpretedAsInteger($value))
        || Tca::table($dataType)->field($fieldName)->isNumerical();
    }

    /**
     * Signal that is called for post-processing a matcher object.
     *
     * @param Matcher $matcher
     * @signal
     */
    protected function emitPostProcessMatcherObjectSignal(Matcher $matcher)
    {
        $this->getSignalSlotDispatcher()->dispatch(MatcherFactory::class, 'postProcessMatcherObject', array($matcher, $matcher->getDataType()));
    }

    /**
     * Get the SignalSlot dispatcher
     *
     * @return Dispatcher
     */
    protected function getSignalSlotDispatcher()
    {
        return $this->getObjectManager()->get(Dispatcher::class);
    }

    /**
     * @return ObjectManager
     */
    protected function getObjectManager()
    {
        return GeneralUtility::makeInstance(ObjectManager::class);
    }

    /**
     * @return FieldPathResolver
     */
    protected function getFieldPathResolver()
    {
        return GeneralUtility::makeInstance(FieldPathResolver::class);
    }

}
