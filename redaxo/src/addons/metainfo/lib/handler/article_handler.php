<?php

/**
 * @package redaxo\metainfo
 */
class rex_metainfo_article_handler extends rex_metainfo_handler
{
    const PREFIX = 'art_';

    protected function handleSave(array $params, rex_sql $sqlFields)
    {
        // Nur speichern wenn auch das MetaForm ausgefüllt wurde
        // z.b. nicht speichern wenn über be_search select navigiert wurde
        if (!rex_post('savemeta', 'boolean')) {
            return $params;
        }

        $article = rex_sql::factory();
        // $article->setDebug();
        $article->setTable(rex::getTablePrefix() . 'article');
        $article->setWhere('id=:id AND clang=:clang', ['id' => $params['id'], 'clang' => $params['clang']]);

        parent::fetchRequestValues($params, $article, $sqlFields);

        // do the save only when metafields are defined
        if ($article->hasValues()) {
            $article->update();
        }

        // Artikel nochmal mit den zusätzlichen Werten neu generieren
        rex_article_cache::generateMeta($params['id'], $params['clang']);

        rex_extension::registerPoint(new rex_extension_point('ART_META_UPDATED', '', $params));

        return $params;
    }

    protected function buildFilterCondition(array $params)
    {
        $restrictionsCondition = '';

        if (!empty($params['id'])) {
            $s = '';
            $OOArt = rex_article::getArticleById($params['id'], $params['clang']);

            // Alle Metafelder des Pfades sind erlaubt
            foreach ($OOArt->getPathAsArray() as $pathElement) {
                if ($pathElement != '') {
                    $s .= ' OR `p`.`restrictions` LIKE "%|' . $pathElement . '|%"';
                }
            }

            $restrictionsCondition = 'AND (`p`.`restrictions` = "" OR `p`.`restrictions` IS NULL ' . $s . ')';
        }

        return $restrictionsCondition;
    }

    protected function renderFormItem($field, $tag, $tag_attr, $id, $label, $labelIt, $typeLabel)
    {
        $s = '';
        if ($typeLabel != 'legend') {
            $s .= '<div class="rex-form-row">';
        }

        if ($tag != '') {
            $s .= '<' . $tag . $tag_attr  . '>' . "\n";
        }

        if ($labelIt) {
            $s .= '<label for="' . $id . '">' . $label . '</label>' . "\n";
        }

        $s .= $field . "\n";

        if ($tag != '') {
            $s .= '</' . $tag . '>' . "\n";
        }

        if ($typeLabel != 'legend') {
            $s .= '</div>';
        }

        return $s;
    }

    public function getForm(array $params)
    {
        $OOArt = rex_article::getArticleById($params['id'], $params['clang']);

        $params['activeItem'] = $params['article'];
        // Hier die category_id setzen, damit beim klick auf den REX_LINK_BUTTON der Medienpool in der aktuellen Kategorie startet
        $params['activeItem']->setValue('category_id', $OOArt->getCategoryId());

        return parent::renderFormAndSave(self::PREFIX, $params);
    }

    public function extendForm(rex_extension_point $ep)
    {
        // noop
    }
}
