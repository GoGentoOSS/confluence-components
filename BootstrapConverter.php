<?php

class BootstrapConverter
{
    protected \DOMDocument $_document;
    protected \DOMNode $_toc;

    protected array $_layoutReplacements = [];

    protected array $_layoutConversion = [
        'two-right-sidebar' => [
            'normal' => 'col-xs-12 col-md-8',
            'aside' => 'col-xs-12 col-md-4'
        ],
        'two-equal' => [
            'normal' => 'col-xs-12 col-md-6',
            'aside' => 'col-xs-12 col-md-6'
        ],
        'two-left-sidebar' => [
            'normal' => 'col-xs-12 col-md-8',
            'aside' => 'col-xs-12 col-md-4'
        ],
        'three-equal' => [
            'normal' => 'col-xs-12 col-md-4',
            'aside' => 'col-xs-12 col-md-4'
        ],
        'three-sidebar' => [
            'normal' => 'col-xs-12 col-md-6',
            'aside' => 'col-xs-12 col-md-2'
        ],
        'single' => [
            'normal' => 'col-xs-12 col-md-12',
            'aside' => 'col-xs-12 col-md-12'
        ]
    ];

    protected array $_classReplacements = [
        'contentLayout2' => 'container',
        'columnLayout' => 'row',
        'cell' => 'col',
        'two-right-sidebar' => '',
        'two-left-sidebar' => '',
        'single' => '',
        'panelContent' => 'panel-body',
        'output-block' => '',
        'conf-macro' => ''
    ];

    protected array $_extraClasses = [
        'panel' => 'panel-default',
        'row' => 'margin-bottom'
    ];

    public function convert($inputData)
    {
        libxml_use_internal_errors(true);

        $this->_document = new \DOMDocument();

        $this->_document->loadHTML($inputData);

        $this->_toc = $this->generateToc($this->_document);
        $this->convertNode($this->_document);
        $this->sanitizeDocument();

        return $this->_document->saveHTML();
    }

    /**
     * @param $htmlContent
     * @return string|string[]|null
     */
    protected function sanitizeDocument()
    {
        $xpath = new \DOMXPath($this->_document);
        $nodes = $xpath->query('//@*');
        foreach($nodes as $node) {
            if(strpos($node->nodeName, 'data-') !== false) {
                $node->parentNode->removeAttribute($node->nodeName);
            }

            if($node->nodeName == 'style') {
                $node->parentNode->removeAttribute($node->nodeName);
            }
        }
    }

    /**
     * @param \DOMNode $domNode
     * @param null $parentNode
     */
    protected function convertNode(\DOMNode $domNode, $parentNode = null) {

        if($domNode instanceof \DOMElement) {
            $this->sanitizeNode($domNode, $parentNode);
        }

        foreach ($domNode->childNodes as $node)
        {
            $this->convertNode($node, $domNode);
        }
    }

    /**
     * @param \DOMElement $node
     * @param null $parentNode
     */
    protected function sanitizeNode(\DOMElement $node, $parentNode = null)
    {
        // First convert styling to classes if needed
        if($node->hasAttribute('style')) {
            if($node->hasAttribute('class')) {
                $classes = $node->getAttribute('class');
            } else {
                $classes = '';
            }

            $style = $node->getAttribute('style');
            if(strpos($style, 'text-align: center') !== false) {
                $classes .= ' text-center';
            }

            if(strpos($style, 'text-align: left') !== false) {
                $classes .= ' text-left';
            }

            if(strpos($style, 'text-align: right') !== false) {
                $classes .= ' text-right';
            }

            $node->setAttribute('class', trim($classes));
        }

        if($node->hasAttribute('class')) {
            $classes = $node->getAttribute('class');

            // Layout handler
            if(strpos($classes, 'columnLayout') !== false) {
                foreach($this->_layoutConversion as $layoutType => $replacements) {
                    if(strpos($classes, $layoutType) !== false) {
                        $this->_layoutReplacements = $replacements;
                        // Found the layout type, as long as we dont get a new definition, it wont change
                    }
                }
            }

            // TOC Handler
            if(strpos($classes, 'toc-macro') !== false) {
                if($parentNode instanceof \DOMNode) {
                    $parentNode->replaceChild($this->_toc, $node);
                }
            }

            foreach(array_merge($this->_classReplacements, $this->_layoutReplacements) as $originalClass => $classReplacement) {
                $classes = str_replace($originalClass, $classReplacement, $classes);
            }

            // Ensure we can add a class which makes bootstrap work better
            foreach($this->_extraClasses as $originalClass => $addedClass) {
                if(strpos($classes, $originalClass . ' ') !== false) {
                    $classes = str_replace($originalClass, $originalClass . ' ' . $addedClass, $classes);
                }
            }

            $node->setAttribute('class', rtrim(preg_replace('/\s+/', ' ', $classes)));
        }
    }

    /**
     * @param \DOMNode $domNode
     * @param null $row
     * @return \DOMNode
     */
    protected function generateToc(\DOMNode $domNode, $row = null): \DOMNode
    {
        if(!$row instanceof \DOMNode) {
            $row = $this->_document->createElement('div');
            $row->setAttribute('class','container');
        }

        foreach ($domNode->childNodes as $node)
        {
            if($node instanceof \DOMElement) {
                if(in_array($node->nodeName, ['h1','h2','h3','h4','h5','h6'])) {
                    $item = $row->appendChild($this->_document->createElement('div'));
                    $item->setAttribute('class','col col-xs-12');

                    $link = $item->appendChild($this->_document->createElement('a'));
                    $item->setAttribute('class','toc-' . $node->nodeName);
                    $link->setAttribute('href', '#' . $node->getAttribute('id'));
                    $link->nodeValue = $node->nodeValue;
                }
            }

            if($node->hasChildNodes()) {
                $toc = $this->generateToc($node, $row);
            }
        }

        return $row;
    }
}