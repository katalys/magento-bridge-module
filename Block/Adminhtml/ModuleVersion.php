<?php

namespace Katalys\Shop\Block\Adminhtml;

use Katalys\Shop\Api\GetModuleDetailsInterface;
use Magento\Backend\Block\AbstractBlock;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Context;

/**
 * Class ModuleVersion
 */
class ModuleVersion extends AbstractBlock implements RendererInterface
{
    /**
     * @var GetModuleDetailsInterface
     */
    protected $getModuleDetails;

    /**
     * @param Template\Context $context
     * @param GetModuleDetailsInterface $getModuleDetails
     * @param array $data
     */
    public function __construct(
        Context $context,
        GetModuleDetailsInterface $getModuleDetails,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->getModuleDetails = $getModuleDetails;
    }

    /**
     * Render element html
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $html = "<tr class='system-fieldset-sub-head' id='row_{$element->getHtmlId()}'>
                    <td class='label'>
                        <h4 id='{$element->getHtmlId()}'>{$element->getLabel()}</h4>
                    </td>
                    <td class='value' style='vertical-align: bottom;font-size: 17px;font-weight: bold;'>";

        if ($this->getModuleDetails->hasNewVersion()) {
            $html .= "<span style='color: #d50000;'>{$this->getModuleDetails->getActualVersion()}</span>";
            $html .= ' -> <span style="color: #2962ff;">' . $this->getModuleDetails->getNewVersion() . '</span>';
        } else {
            $html .= "<span>{$this->getModuleDetails->getActualVersion()}</span>";
        }

        $html .= "</td></tr>";
        return $html;
    }
}
