<?php
namespace Priority\Api\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Responsepopup extends Column
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * Constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
		$this->scopeConfig = $scopeConfig;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
		
		  if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as & $item) { 
				if($item['json_response'] != ""){
					$item[$fieldName . '_html'] = "<button class='button'><span>View</span></button>";
					$item[$fieldName . '_title'] = __('JSON Response');
					$item[$fieldName . '_content'] = $item['json_response'];
				}
            }
        }
		
        return $dataSource;
    }
}