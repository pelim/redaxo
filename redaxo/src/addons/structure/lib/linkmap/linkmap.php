<?php

/**
 * @package redaxo\structure
 */
class rex_linkmap_category_tree extends rex_linkmap_tree_renderer
{
    private $context;

    public function __construct(rex_context $context)
    {
        $this->context = $context;
    }

    protected function treeItem(rex_category $cat, $liClasses, $linkClasses, $subHtml)
    {

        if ($liClasses != '') {
            $liClasses = ' class="' . rtrim($liClasses) . '"';
        }

        if ($linkClasses != '') {
            $linkClasses = ' class="' . rtrim($linkClasses) . '"';
        }

        $label = self::formatLabel($cat);

        $li = '';
        $li .= '            <li' . $liClasses . '>';
        $li .= '<a' . $linkClasses . ' href="' . $this->context->getUrl(['category_id' => $cat->getId()]) . '">' . htmlspecialchars($label) . '</a>';
        $li .= $subHtml;
        $li .= '</li>' . "\n";

        return $li;
    }
}

/**
 * @package redaxo\structure
 */
class rex_linkmap_article_list extends rex_linkmap_article_list_renderer
{
    private $context;

    public function __construct(rex_context $context)
    {
        $this->context = $context;
    }

    protected function listItem(rex_article $article, $category_id)
    {
        $liClass = $article->isStartArticle() ? ' class="rex-linkmap-startarticle"' : '';
        $url     = 'javascript:insertLink(\'redaxo://' . $article->getId() . '\',\'' . addslashes(htmlspecialchars($article->getName())) . '\');';
        return rex_linkmap_tree_renderer::formatLi($article, $category_id, $this->context, $liClass, ' href="' . $url . '"') . '</li>' . "\n";
    }
}
