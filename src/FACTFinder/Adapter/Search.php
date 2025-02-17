<?php
namespace FACTFinder\Adapter;

use FACTFinder\Loader as FF;

class Search extends PersonalisedResponse
{
    
    /**
     * @var FACTFinder\Util\LoggerInterface
     */
    private $log;

    /**
     * @var FACTFinder\Data\Result
     */
    private $result;

    /**
     * @var FACTFinder\Data\SingleWordSearchItem[]
     */
    private $singleWordSearch;

    /**
     * @var FACTFinder\Data\AfterSearchNavigation
     */
    private $afterSearchNavigation;

    /**
     * @var FACTFinder\Data\ResultsPerPageOptions
     */
    private $resultsPerPageOptions;

    /**
     * @var FACTFinder\Data\Paging
     */
    private $paging;

    /**
     * @var FACTFinder\Data\Sorting
     */
    private $sorting;

    /**
     * @var FACTFinder\Data\SortingItems
     */
    private $sortingItems;

    /**
     * @var FACTFinder\Data\BreadCrumbTrail
     */
    private $breadCrumbTrail;

    /**
     * @var FACTFinder\Data\CampaignIterator
     */
    private $campaigns;

    public function __construct(
        $loggerClass,
        \FACTFinder\Core\ConfigurationInterface $configuration,
        \FACTFinder\Core\Server\Request $request,
        \FACTFinder\Core\Client\UrlBuilder $urlBuilder,
        \FACTFinder\Core\AbstractEncodingConverter $encodingConverter = null
    ) {
        parent::__construct($loggerClass, $configuration, $request,
                            $urlBuilder, $encodingConverter);

        $this->log = $loggerClass::getLogger(__CLASS__);

        $this->request->setAction('Search.ff');
        $this->parameters['format'] = 'json';

        $this->useJsonResponseContentProcessor();
    }

    /**
     * Overwrite the query on the request.
     * @param string $query
     */
    public function setQuery($query)
    {
        $this->parameters['query'] = $query;
    }

    /**
     * @return \FACTFinder\Data\Result
     */
    public function getResult()
    {
        if (is_null($this->result) || !$this->upToDate) {
            $this->request->resetLoaded();
            $this->result = $this->createResult();
            $this->upToDate = true;
        }

        return $this->result;
    }

    /**
     * @return \FACTFinder\Data\Result
     */
    private function createResult()
    {
        //init default values
        $records      = array();
        $resultCount = 0;

        $jsonData = $this->getResponseContent();

        if ($this->isValidResponse($jsonData)) {
            $searchResultData = $jsonData['searchResult'];

            if (!empty($searchResultData['records']))
            {
                $resultCount = $searchResultData['resultCount'];

                foreach ($searchResultData['records'] as $recordData)
                {
                    $position = $recordData['position'];

                    $record = FF::getInstance('Data\Record',
                        (string)$recordData['id'],
                        $recordData['record'],
                        $recordData['searchSimilarity'],
                        $position,
                        isset($recordData['seoPath']) ? $recordData['seoPath'] : '',
                        $recordData['keywords']
                    );

                    $records[] = $record;
                }
            }
        }

        return FF::getInstance(
            'Data\Result',
            $records,
            $resultCount
        );
    }

    /**
     * @return \FACTFinder\Data\SingleWordSearchItem[]
     */
    public function getSingleWordSearch()
    {
        if (is_null($this->singleWordSearch))
            $this->singleWordSearch = $this->createSingleWordSearch();

        return $this->singleWordSearch;
    }

    /**
     * @return \FACTFinder\Data\SingleWordSearchItem[]
     */
    private function createSingleWordSearch()
    {
        $singleWordSearch = array();

        $jsonData = $this->getResponseContent();
        if ($this->isValidResponse($jsonData) && !empty($jsonData['searchResult']['singleWordResults']))
        {
            foreach ($jsonData['searchResult']['singleWordResults'] as $swsData)
            {
                $item = FF::getInstance(
                    'Data\SingleWordSearchItem',
                    $swsData['word'],
                    $this->convertServerQueryToClientUrl(
                        $swsData['searchParams']
                    ),
                    $swsData['recordCount']
                );

                foreach ($swsData['previewRecords'] as $recordData)
                {
                    $item->addPreviewRecord(FF::getInstance('Data\Record',
                        (string)$recordData['id'],
                        $recordData['record'],
                        $recordData['searchSimilarity'],
                        $recordData['position'],
                        '',
                        $recordData['keywords']
                    ));
                }

                $singleWordSearch[] = $item;
            }
        }

        return $singleWordSearch;
    }

    /**
     * @return \FACTFinder\Data\SearchStatus
     */
    public function getStatus()
    {
        $searchStatusEnum = FF::getClassName('Data\SearchStatus');
        $status = $searchStatusEnum::NoResult();
                
        $jsonData = $this->getResponseContent();
        if ($this->isValidResponse($jsonData)) 
        {
            switch($jsonData['searchResult']['resultStatus'])
            {
            case 'nothingFound':
                $status = $searchStatusEnum::EmptyResult();
                break;
            case 'resultsFound':
                $status = $searchStatusEnum::RecordsFound();
                break;
            }
        }
        return $status;
    }

    /**
     * @return \FACTFinder\Data\ArticleNumberSearchStatus
     */
    public function getArticleNumberStatus()
    {   
        $articleNumberSearchStatusEnum = FF::getClassName('Data\ArticleNumberSearchStatus');
        $status = $articleNumberSearchStatusEnum::IsNoArticleNumberSearch();
        
        $jsonData = $this->getResponseContent();
        if ($this->isValidResponse($jsonData)) 
        {
            switch ($jsonData['searchResult']['resultArticleNumberStatus']) 
            {
            case 'resultsFound':
                $status = $articleNumberSearchStatusEnum::IsArticleNumberResultFound();
                break;
            case 'nothingFound':
                $status = $articleNumberSearchStatusEnum::IsNoArticleNumberResultFound();
                break;
            }
        }
        return $status;
    }

    /**
     * @return bool
     */
    public function isSearchTimedOut()
    {
        $jsonData = $this->getResponseContent();
        if ($this->isValidResponse($jsonData))
        {
            return $jsonData['searchResult']['timedOut'];
        }
        return true;
    }

    /**
     * @return \FACTFinder\Data\AfterSearchNavigation
     */
    public function getAfterSearchNavigation()
    {
        if (is_null($this->afterSearchNavigation))
            $this->afterSearchNavigation = $this->createAfterSearchNavigation();

        return $this->afterSearchNavigation;
    }

    /**
     * @return \FACTFinder\Data\AfterSearchNavigation
     */
    private function createAfterSearchNavigation()
    {
        $jsonData = $this->getResponseContent();

        $filterGroups = array();

        if ($this->isValidResponse($jsonData) && isset($jsonData['searchResult']['groups'])) {
            foreach ($jsonData['searchResult']['groups'] as $groupData)
                $filterGroups[] = $this->createFilterGroup($groupData);
        }

        return FF::getInstance(
            'Data\AfterSearchNavigation',
            $filterGroups
        );
    }

    /**
     * @param mixed[] $groupData An associative array corresponding to the JSON
     *        for a single filter group.
     * @return \FACTFinder\Data\FilterGroup
     */
    private function createFilterGroup($groupData)
    {
        $elements = array_merge(
            $groupData['selectedElements'],
            $groupData['elements']
        );

        $filterStyleEnum = FF::getClassName('Data\FilterStyle');
        switch ($groupData['filterStyle'])
        {
        case 'SLIDER':
            $filterStyle = $filterStyleEnum::Slider();
            break;
        case 'TREE':
            $filterStyle = $filterStyleEnum::Tree();
            break;
        case 'MULTISELECT':
            $filterStyle = $filterStyleEnum::MultiSelect();
            break;
        default:
            $filterStyle = $filterStyleEnum::Regular();
            break;
        }

        $filters = array();
        foreach ($elements as $filterData)
        {
            if ($filterStyle == $filterStyleEnum::Slider())
                $filters[] = $this->createSliderFilter($filterData);
            else
                $filters[] = $this->createFilter($filterData);
        }

        $filterSelectionType = null;
        $filterSelectionTypeEnum = FF::getClassName('Data\FilterSelectionType');
        if (isset($groupData['selectionType']))
        {
            switch ($groupData['selectionType'])
            {
            case 'multiSelectOr':
                $filterSelectionType = $filterSelectionTypeEnum::MultiSelectOr();
                break;
            case 'multiSelectAnd':
                $filterSelectionType = $filterSelectionTypeEnum::MultiSelectAnd();
                break;
            case 'singleShowUnselected':
                $filterSelectionType = $filterSelectionTypeEnum::SingleShowUnselected();
                break;
            default:
                $filterSelectionType = $filterSelectionTypeEnum::SingleHideUnselected();
                break;
            }
        }

        $filterType = null;
        $filterTypeEnum = FF::getClassName('Data\FilterType');
        if (isset($groupData['type']))
        {
            switch ($groupData['type'])
            {
            case 'number':
                $filterType = $filterTypeEnum::Number();
                break;
            default:
                $filterType = $filterTypeEnum::Text();
                break;
            }
        }

        return FF::getInstance(
            'Data\FilterGroup',
            $filters,
            $groupData['name'],
            $filterStyle,
            $groupData['decimalPlaces'],
            $groupData['detailedLinks'],
            $groupData['unit'],
            $filterSelectionType,
            $filterType,
            $groupData['showPreviewImages']
        );
    }

    /**
     * @param mixed[] $filterData An associative array corresponding to the JSON
     *        for a single filter.
     * @return \FACTFinder\Data\Filter
     */
    private function createFilter(array $filterData)
    {
        $filterLink = $this->convertServerQueryToClientUrl(
            $filterData['searchParams']
        );

        return FF::getInstance(
            'Data\Filter',
            $filterData['name'],
            $filterLink,
            $filterData['selected'],
            $filterData['associatedFieldName'],
            $filterData['recordCount'],
            $filterData['clusterLevel'],
            $filterData['previewImageURL'] ?: ''
        );
    }

    /**
     * @param mixed[] $filterData An associative array corresponding to the JSON
     *        for a single slider filter.
     * @return \FACTFinder\Data\SliderFilter
     */
    private function createSliderFilter(array $filterData)
    {
        // For sliders, FACT-Finder appends a filter parameter without value to
        // the 'searchParams' field, which is to be filled with the selected
        // minimum and maximum like 'filterValue=min-max'.
        // We split that parameter off, and treat it separately to ensure that
        // it stays the last parameter when converted to a client URL.
        preg_match(
            '/
            (.*)            # match and capture as much of the query as possible
            [?&]filter      # match "?filter" or "&filter" literally
            ([^&=]*)        # group 2, the field name
            =(?=$|&)        # make sure there is a "=" followed by the end of
                            # the string or another parameter
            (.*)            # match the remainder of the query
            /x',
            $filterData['searchParams'],
            $matches
        );

        if(!empty($matches)) {
         $query = $matches[1] . $matches[3];
            $fieldName = $matches[2];
        } else {
            // The URL of searchParams was not as expected, propably the current filter was not
            // added as an empty parameter. Therefore no need to remove it and we can use full searchParams URL.
            $query = $filterData['searchParams'];
            $fieldName = $filterData['associatedFieldName'];
        }

        if (urldecode($fieldName) != $filterData['associatedFieldName'])
            $this->log->warn('Filter parameter of slider does not correspond '
                           . 'to transmitted "associatedFieldName". Parameter: '
                           . "$fieldName. Field name: "
                           . $filterData['associatedFieldName'] . '.');

        $filterLink = $this->convertServerQueryToClientUrl($query);

        return FF::getInstance(
            'Data\SliderFilter',
            $filterLink,
            $fieldName,
            $filterData['absoluteMinValue'],
            $filterData['absoluteMaxValue'],
            $filterData['selectedMinValue'],
            $filterData['selectedMaxValue']
        );
    }

    /**
     * @return \FACTFinder\Data\ResultsPerPageOptions
     */
    public function getResultsPerPageOptions()
    {
        if (is_null($this->resultsPerPageOptions))
            $this->resultsPerPageOptions = $this->createResultsPerPageOptions();

        return $this->resultsPerPageOptions;
    }

    /**
     * @return \FACTFinder\Data\ResultsPerPageOptions
     */
    public function createResultsPerPageOptions()
    {
        $options = array();

        $defaultOption = null;
        $selectedOption = null;

        $jsonData = $this->getResponseContent();

        if ($this->isValidResponse($jsonData))
        {
            $rppData = $jsonData['searchResult']['resultsPerPageList'];
            if (!empty($rppData))
            {
                foreach ($rppData as $optionData)
                {
                    $optionLink = $this->convertServerQueryToClientUrl(
                        $optionData['searchParams']
                    );

                    $option = FF::getInstance(
                        'Data\Item',
                        $optionData['value'],
                        $optionLink,
                        $optionData['selected']
                    );

                    if ($optionData['default'])
                        $defaultOption = $option;
                    if ($optionData['selected'])
                        $selectedOption = $option;

                    $options[] = $option;
                }
            }
            return FF::getInstance(
                'Data\ResultsPerPageOptions',
                $options,
                $defaultOption,
                $selectedOption
            );
        }
        return null;
    }

    /**
     * @return \FACTFinder\Data\Paging
     */
    public function getPaging()
    {
        if (is_null($this->paging))
            $this->paging = $this->createPaging();

        return $this->paging;
    }

    /**
     * @return \FACTFinder\Data\Paging
     */
    private function createPaging()
    {
        $pages = array();

        $jsonData = $this->getResponseContent();

        if ($this->isValidResponse($jsonData))
        {
            $pagingData = $jsonData['searchResult']['paging'];
            if (!empty($pagingData))
            {
                $currentPage = null;
                $pageCount = $pagingData['pageCount'];

                foreach ($pagingData['pageLinks'] as $pageData)
                {
                    $page = $this->createPageItem($pageData);

                    if ($pageData['currentPage'])
                        $currentPage = $page;

                    $pages[] = $page;
                }
            }

            if (!$currentPage)
                $currentPage = FF::getInstance(
                    'Data\Page',
                    $pagingData['currentPage'],
                    $pagingData['currentPage'],
                    '#',
                    true
            );
            return FF::getInstance(
                'Data\Paging',
                $pages,
                $pageCount,
                $currentPage,
                $this->createPageItem($pagingData['firstLink']),
                $this->createPageItem($pagingData['lastLink']),
                $this->createPageItem($pagingData['previousLink']),
                $this->createPageItem($pagingData['nextLink'])
            );
        }
        return null;
    }


    /**
     * @param mixed[] $pageData An associative array corresponding to the JSON
     *        for a single page link.
     * @return \FACTFinder\Data\Item
     */
    private function createPageItem(array $pageData = null)
    {
        if (is_null($pageData))
            return null;

        $pageLink = $this->convertServerQueryToClientUrl(
            $pageData['searchParams']
        );

        return FF::getInstance(
            'Data\Page',
            $pageData['number'],
            $pageData['caption'],
            $pageLink,
            $pageData['currentPage']
        );
    }

    /**
     * @return \FACTFinder\Data\Sorting
     */
    public function getSorting()
    {
        if (is_null($this->sorting))
            $this->sorting = $this->createSorting();

        return $this->sorting;
    }

    /**
     * @return \FACTFinder\Data\Sorting
     */
    private function createSorting()
    {
        $sortOptions = array();

        $jsonData = $this->getResponseContent();

        if ($this->isValidResponse($jsonData))
        {
            $sortingData = $jsonData['searchResult']['sortsList'];
            if (!empty($sortingData))
            {
                foreach ($sortingData as $optionData)
                {
                    $optionLink = $this->convertServerQueryToClientUrl(
                        $optionData['searchParams']
                    );

                    $sortOptions[] = FF::getInstance(
                        'Data\Item',
                        $optionData['description'],
                        $optionLink,
                        $optionData['selected']
                    );
                }
            }

            return FF::getInstance(
                'Data\Sorting',
                $sortOptions
            );
        }
        return null;
    }

    /**
     * @return \FACTFinder\Data\SortingItems
     */
    public function getSortingItems()
    {
        if (is_null($this->sortingItems))
            $this->sortingItems = $this->createSortingItems();

        return $this->sortingItems;
    }

    /**
     * @return \FACTFinder\Data\SortingItems
     */
    private function createSortingItems()
    {
        $sortOptions = array();

        $jsonData = $this->getResponseContent();

        if ($this->isValidResponse($jsonData))
        {
            $sortingData = $jsonData['searchResult']['sortsList'];
            if (!empty($sortingData))
            {
                $orderEnum = FF::getClassName('Data\SortingDirection');
                foreach ($sortingData as $optionData)
                {
                    $optionLink = $this->convertServerQueryToClientUrl(
                        $optionData['searchParams']
                    );
                    if (isset($optionData['order']))
                    {
                        switch ($optionData['order'])
                        {
                            case 'asc':
                                $order = $orderEnum::Ascending();
                                break;
                            case 'desc':
                            default:
                                $order = $orderEnum::Descending();
                                break;
                        }
                    }

                    $sortOptions[] = FF::getInstance(
                        'Data\SortingItem',
                        $optionData['name'],
                        $order,
                        $optionData['description'],
                        $optionLink,
                        $optionData['selected']
                    );
                }
            }

            return FF::getInstance(
                'Data\SortingItems',
                $sortOptions
            );
        }
        return null;
    }

    /**
     * @return \FACTFinder\Data\BreadCrumbTrail
     */
    public function getBreadCrumbTrail()
    {
        if (is_null($this->breadCrumbTrail))
            $this->breadCrumbTrail = $this->createBreadCrumbTrail();

        return $this->breadCrumbTrail;
    }

    /**
     * @return \FACTFinder\Data\BreadCrumbTrail
     */
    private function createBreadCrumbTrail()
    {
        $breadCrumbs = array();

        $jsonData = $this->getResponseContent();

        if ($this->isValidResponse($jsonData))
        {
            $breadCrumbTrailData = $jsonData['searchResult']['breadCrumbTrailItems'];
            if (!empty($breadCrumbTrailData))
            {
                $i = 1;
                foreach ($breadCrumbTrailData as $breadCrumbData)
                {
                    $breadCrumbLink = $this->convertServerQueryToClientUrl(
                        $breadCrumbData['searchParams']
                    );

                    $breadCrumbTypeEnum = FF::getClassName('Data\BreadCrumbType');
                    switch ($breadCrumbData['type'])
                    {
                    case 'filter':
                        $type = $breadCrumbTypeEnum::Filter();
                        break;
                    case 'advisor':
                        $type = $breadCrumbTypeEnum::Advisor();
                        break;
                    default:
                        $type = $breadCrumbTypeEnum::Search();
                        break;
                    }
                    
                    $breadCrumbs[] = FF::getInstance(
                        'Data\BreadCrumb',
                        $breadCrumbData['text'],
                        $breadCrumbLink,
                        $i == count($breadCrumbTrailData),
                        $type,
                        $breadCrumbData['associatedFieldName']
                    );

                    ++$i;
                }
            }
        }
        
        return FF::getInstance(
            'Data\BreadCrumbTrail',
            $breadCrumbs
        );
    }

    /**
     * @return \FACTFinder\Data\CampaignIterator
     */
    public function getCampaigns()
    {
        if (is_null($this->campaigns))
            $this->campaigns = $this->createCampaigns();

        return $this->campaigns;
    }

    /**
     * @return \FACTFinder\Data\CampaignIterator
     */
    private function createCampaigns()
    {
        $campaigns = array();
        $jsonData = $this->getResponseContent();

        if ($this->isValidResponse($jsonData) && isset($jsonData['searchResult']['campaigns'])) {
            foreach ($jsonData['searchResult']['campaigns'] as $campaignData) {
                $campaign = $this->createEmptyCampaignObject($campaignData);

                $this->fillCampaignObject($campaign, $campaignData);

                $campaigns[] = $campaign;
            }
        }

        $campaignIterator = FF::getInstance(
            'Data\CampaignIterator',
            $campaigns
        );
        return $campaignIterator;
    }

    /**
     * @param mixed[] $campaignData An associative array corresponding to the
     *        JSON for a single campaign.
     * @return \FACTFinder\Data\Campaign
     */
    private function createEmptyCampaignObject(array $campaignData)
    {
        return FF::getInstance(
            'Data\Campaign',
            $campaignData['name'],
            $campaignData['category'],
            $campaignData['target']['destination']
        );
    }

    /**
     * @param \FACTFinder\Data\Campaign $campaign The campaign object to be
     *        filled.
     * @param mixed[] $campaignData An associative array corresponding to the
     *        JSON for that campaign.
     */
    private function fillCampaignObject(
        \FACTFinder\Data\Campaign $campaign,
        array $campaignData
    ) {
        switch ($campaignData['flavour'])
        {
        case 'FEEDBACK':
            $this->fillCampaignWithFeedback($campaign, $campaignData);
            $this->fillCampaignWithPushedProducts($campaign, $campaignData);
            break;
        case 'ADVISOR':
            $this->fillCampaignWithAdvisorData($campaign, $campaignData);
            break;
        }
    }

    /**
     * @param \FACTFinder\Data\Campaign $campaign The campaign object to be
     *        filled.
     * @param mixed[] $campaignData An associative array corresponding to the
     *        JSON for that campaign.
     */
    protected function fillCampaignWithFeedback(
        \FACTFinder\Data\Campaign $campaign,
        array $campaignData
    ) {
        if (!empty($campaignData['feedbackTexts']))
        {
            $feedback = array();

            foreach ($campaignData['feedbackTexts'] as $feedbackData)
            {
                // If present, add the feedback to both the label and the ID.
                $html = $feedbackData['html'];
                $text = $feedbackData['text'];
                if (!$html)
                {
                    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                }

                $label = $feedbackData['label'];
                if ($label !== '')
                    $feedback[$label] = $text;

                $id = $feedbackData['id'];
                if ($id !== null)
                    $feedback[$id] = $text;
            }

            $campaign->addFeedback($feedback);
        }
    }

    /**
     * @param \FACTFinder\Data\Campaign $campaign The campaign object to be
     *        filled.
     * @param mixed[] $campaignData An associative array corresponding to the
     *        JSON for that campaign.
     */
    private function fillCampaignWithPushedProducts(
        \FACTFinder\Data\Campaign $campaign,
        array $campaignData
    ) {
        if (!empty($campaignData['pushedProductsRecords']))
        {
            $pushedProducts = array();

            foreach ($campaignData['pushedProductsRecords'] as $recordData)
            {
                $pushedProducts[] = FF::getInstance(
                    'Data\Record',
                    (string)$recordData['id'],
                    $recordData['record']
                );
            }

            $campaign->addPushedProducts($pushedProducts);
        }
    }

    /**
     * @param \FACTFinder\Data\Campaign $campaign The campaign object to be
     *        filled.
     * @param mixed[] $campaignData An associative array corresponding to the
     *        JSON for that campaign.
     */
    private function fillCampaignWithAdvisorData(
        \FACTFinder\Data\Campaign $campaign,
        array $campaignData
    ) {
        $activeQuestions = array();

        foreach ($campaignData['activeQuestions'] as $questionData)
            $activeQuestions[] = $this->createAdvisorQuestion($questionData);

        $campaign->addActiveQuestions($activeQuestions);

        // Fetch advisor tree if it exists
        $advisorTree = array();

        foreach ($campaignData['advisorTree'] as $questionData)
            $advisorTree[] = $this->createAdvisorQuestion($questionData,
                                                               true);

        $campaign->addToAdvisorTree($advisorTree);
    }

    /**
     * @param mixed[] $questionData An associative array corresponding to the
     *        JSON for a single advisor question.
     * @param bool $recursive If this is set the entire advisor tree below this
     *        question will be created. Otherwise, follow-up questions of
     *        answers are omitted.
     */
    private function createAdvisorQuestion($questionData, $recursive = false)
    {
        $answers = array();

        foreach ($questionData['answers'] as $answerData)
            $answers[] = $this->createAdvisorAnswer($answerData, $recursive);

        return FF::getInstance('Data\AdvisorQuestion',
            $questionData['text'],
            $answers
        );
    }

    /**
     * @param mixed[] $answerData An associative array corresponding to the
     *        JSON for a single advisor answer.
     * @param bool $recursive If this is set the entire advisor tree below the
     *        subquestion of this ansewr will be created as well.
     */
    private function createAdvisorAnswer($answerData, $recursive = false)
    {
        $params =  $this->convertServerQueryToClientUrl(
            $answerData['params']
        );

        $followUpQuestions = array();
        if ($recursive)
            foreach ($answerData['questions'] as $questionData)
                $followUpQuestions[] = $this->createAdvisorQuestion(
                    $questionData,
                    true
                );

        return FF::getInstance('Data\AdvisorAnswer',
            $answerData['text'],
            $params,
            $followUpQuestions
        );
    }
    
    /**
     * Value for parameter "followSearch" for followups on initial search like filters, pagination, ...
     * Either from search results searchParams, request parameters or from search results "simiFirstRecord".
     * Returns 0 if no parameter "followSearch" could be acquired.
     * 
     * @return int
     */
    public function getFollowSearchValue()
    {
        $jsonData = $this->getResponseContent();
        //use searchParams of result if available
        if ($this->isValidResponse($jsonData) && isset($jsonData['searchResult']['searchParams'])) {
            $parameters = FF::getInstance(
                'Util\Parameters',
                $jsonData['searchResult']['searchParams']
            );
        //fallback to current request
        } else {
            $parameters = $this->parameters;
        }
        $searchParameters = FF::getInstance(
            'Data\SearchParameters',
            $parameters
        );
        $sorting = $searchParameters->getSortings();
        // check if followSearch was set in request data or sent by FF in result searchParams
        if($searchParameters->getFollowSearch() !== 0) {
            $followSearch =  $searchParameters->getFollowSearch();
        // use simiFirstRecord only if result was not sorted
        } elseif (empty($sorting)) {
            $jsonData = $this->getResponseContent();
            if($jsonData && $jsonData['searchResult'] && isset($jsonData['searchResult']['simiFirstRecord']))
                $followSearch = $jsonData['searchResult']['simiFirstRecord'];
        //mark as not valid
        } else {
            $followSearch = 0;
        }
        return $followSearch;
    }
       
    /**
     * Returns true if the search response is valid or false if an error occurred.
     *
     * @return bool
     */
    protected function isValidResponse($jsonData)
    {
        return (!empty($jsonData) && !isset($jsonData['error']) && isset($jsonData['searchResult']));
    }
}
