<?php
namespace minepark\common;

use minepark\Core;
use minepark\Providers;
use pocketmine\utils\Config;
use minepark\defaults\ProtocolConstants;
use pocketmine\Server;

class MDC
{
    private $address;

    private $token;

    private $unitId;

    public function getCore() : Core
    {
        return Core::getActive();
    }

    public function getAddress() : string 
    {
        return $this->address;
    }

    public function getUnitId() : string 
    {
        return $this->unitId;
    }

    public function initializeAll() 
    {
        $this->initializeConfig();
        $this->checkProtocolVersion();
    }

    public function createRequest(string $remoteController, string $remoteMethod, $data)
    {
        $url = "http://" . $this->address . "/$remoteController/$remoteMethod";

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data->scalar ?? $data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Content-type: application/json",
            "Authorization: " . $this->token,
            "UnitId: " . $this->unitId
        ]);

        $result = curl_exec($curl);

        curl_close($curl);

        return json_decode($result, true);
    }

    private function initializeConfig() 
    {
        $file = $this->getCore()->getTargetDirectory() . "mdc.yml";

        $config = new Config($file, Config::YAML, [
            "Address" => "127.0.0.1:19000",
            "AccessToken" => "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
            "UnitId" => "MilkyWay"
        ]);
        
        $this->address = $config->get("Address");
        $this->token = $config->get("AccessToken");
        $this->unitId = $config->get("UnitId");
    }

    private function checkProtocolVersion()
    {
        $expectedProtocolVersion = Providers::getSettingsDataProvider()->getProtocolVersion();
        $actualProtocolVersion = ProtocolConstants::MDC_PROTOCOL_VERSION;

        if($expectedProtocolVersion !== $actualProtocolVersion) {
            $server = Server::getInstance();

            $server->getLogger()->emergency("Invalid MDC Protocol Version!");
            $server->getLogger()->emergency("MDC expected version = $expectedProtocolVersion");
            $server->getLogger()->emergency("MineParkCore actual version = $actualProtocolVersion");

            $server->forceShutdown();
        }
    }
}