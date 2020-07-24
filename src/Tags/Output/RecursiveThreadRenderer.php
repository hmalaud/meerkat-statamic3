<?php

namespace Stillat\Meerkat\Tags\Output;

use Statamic\Facades\Parse;

class RecursiveThreadRenderer
{

    public static function renderRecursiveThread($template, $data, $context, $collectionName)
    {
        $nestedTagRegex = '/\{\{\s*' . $collectionName . '\s*\}\}.*?\{\{\s*\/' . $collectionName . '\s*\}\}/ms';
        preg_match($nestedTagRegex, $template, $match);

        $subKey = 'meerkat_comments_tags_' . md5(time());

        if ($match && count($match) > 0) {
            $nestedCommentsString = $match[0];
            // Remove tag pair from the original template.

            $template = preg_replace($nestedTagRegex, $subKey, $template);

            // Create some regexes to find the opening and closing comments.
            $openingTagRegex = '/\{\{\s*' . $collectionName . '\s*\}\}/ms';
            $closingTagRegex = '/\{\{\s*\/' . $collectionName . '\s*\}\}/ms';

            // We need to remove the opening and closing tag pairs from the template.
            $nestedCommentsString = preg_replace($openingTagRegex, '', $nestedCommentsString);
            $nestedCommentsString = preg_replace($closingTagRegex, '', $nestedCommentsString);


            $commentData = $data[$collectionName];

            $nestedCommentsString = trim($nestedCommentsString);

            $tempContent = Parse::templateLoop($nestedCommentsString, $commentData, true, $context);
            // At this point, we need to render the template without the Meerkat comments tags.
            $subTemplate = Parse::template($template, $data, $context);

            return str_replace($subKey, $tempContent, $subTemplate);
        }
    }

}