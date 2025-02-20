<?php
namespace minepark\external\service\script;

use minepark\Providers;
use minepark\defaults\Defaults;
use minepark\providers\LocalizationProvider;

class LangUpdateScript extends Script
{
    private LocalizationProvider $localizationProvider;

    public function __construct()
    {
        $this->localizationProvider = Providers::getLocalizationProvider();
    }

    public function getName(): string
    {
        return "lang-update";
    }

    public function execute(array $arguments = array())
    {
        $this->info("Updating languages files...");

        $data = $this->localizationProvider->getLangData();
        $mainLang = $data[Defaults::DEFAULT_LANGUAGE_KEY];

        foreach(array_keys($data) as $langKey) {
            if($langKey == Defaults::DEFAULT_LANGUAGE_KEY) {
                continue;
            }

            foreach($mainLang as $stringKey => $value) {
                if(!isset($data[$langKey][$stringKey])) {
                    $data[$langKey][$stringKey] = $value;
                }
            }

            $json = json_encode($data[$langKey], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $json = $this->clearJSON($json);
            file_put_contents($this->localizationProvider->getFileSource($langKey), $json);
        }

        $this->info("OK.");
    }

    private function clearJSON(string $json) : string
    {
        return str_replace("\/", "/", $json);
    }
}