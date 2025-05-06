<?php
namespace App\Helpers;

use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationHelper
{
    public static function translateText($text, $targetLanguage, $sourceLanguage = 'en')
    {
        try {
            $translator = new GoogleTranslate();
            return $translator->setSource($sourceLanguage)->setTarget($targetLanguage)->translate($text);
        } catch (\Exception $e) {
            return $text; // Return original text if translation fails
        }
    }
}
