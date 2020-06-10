<?php
namespace Priority\Api\Magento\Framework\View\TemplateEngine\Xhtml;

class Template extends \Magento\Framework\View\TemplateEngine\Xhtml\Template {
   
    public function append($content)
    {
        $target = $this->templateNode->ownerDocument;
        $source = new \DOMDocument();
        $source->loadXml($content, LIBXML_PARSEHUGE);
        $this->templateNode->appendChild(
            $target->importNode($source->documentElement, TRUE)
        );
    }
}