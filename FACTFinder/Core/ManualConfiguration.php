<?php
namespace FACTFinder\Core;

class ManualConfiguration implements ConfigurationInterface
{
    const HTTP_AUTHENTICATION     = 'http';
    const SIMPLE_AUTHENTICATION   = 'simple';
    const ADVANCED_AUTHENTICATION = 'advanced';

    private $configuration = array();

    function __construct($configuration)
    {
        $this->configuration = $configuration;
    }

    function __set($name, $value)
    {
        $this->configuration[$name] = $value;
    }

    public function getCustomValue($name)
    {
        return $this->configuration[$name];
    }

    public function getVersion()
    {
        return $this->configuration['version'];
    }

    public function getRequestProtocol()
    {
        return $this->configuration['requestProtocol'];
    }

    public function getServerAddress()
    {
        return $this->configuration['serverAddress'];
    }

    public function getServerPort()
    {
        return $this->configuration['port'];
    }

    public function getContext()
    {
        return $this->configuration['context'];
    }

    public function getChannel()
    {
        return $this->configuration['channel'];
    }

    public function getLanguage()
    {
        return $this->configuration['language'];
    }

    public function isHttpAuthenticationType()
    {
        return $this->retrieveType() == self::HTTP_AUTHENTICATION;
    }

    public function isSimpleAuthenticationType()
    {
        return $this->retrieveType() == self::SIMPLE_AUTHENTICATION;
    }

    public function isAdvancedAuthenticationType()
    {
        return $this->retrieveType() == self::ADVANCED_AUTHENTICATION;
    }

    private function retrieveType()
    {
        return $this->configuration['authenticationType'];
    }

    public function getUserName()
    {
        return $this->configuration['userName'];
    }

    public function getPassword()
    {
        return $this->configuration['password'];
    }

    public function getAuthenticationPrefix()
    {
        return $this->configuration['authenticationPrefix'];
    }

    public function getAuthenticationPostfix()
    {
        return $this->configuration['postfix'];
    }

    public function getClientMappings()
    {
        return $this->configuration['clientMappings'];
    }

    public function getServerMappings()
    {
        return $this->configuration['serverMappings'];
    }

    public function getIgnoredClientParameters()
    {
        return $this->configuration['ignoredClientParameters'];
    }

    public function getIgnoredServerParameters()
    {
        return $this->configuration['ignoredServerParameters'];
    }

    public function getRequiredClientParameters()
    {
        return $this->configuration['requiredClientParameters'];
    }

    public function getRequiredServerParameters()
    {
        return $this->configuration['requiredServerParameters'];
    }

    public function getDefaultConnectTimeout()
    {
        return $this->configuration['defaultConnectTimeout'];
    }

    public function getDefaultTimeout()
    {
        return $this->configuration['defaultTimeout'];
    }

    public function getSuggestConnectTimeout()
    {
        return $this->configuration['suggestConnectTimeout'];
    }

    public function getSuggestTimeout()
    {
        return $this->configuration['suggestTimeout'];
    }

    public function getScicConnectTimeout()
    {
        return $this->configuration['scicConnectTimeout'];
    }

    public function getScicTimeout()
    {
        return $this->configuration['scicTimeout'];
    }

    public function getImportConnectTimeout()
    {
        return $this->configuration['importConnectTimeout'];
    }

    public function getImportTimeout()
    {
        return $this->configuration['importTimeout'];
    }

    public function getPageContentEncoding()
    {
        return $this->configuration['pageContentEncoding'];
    }

    public function getClientUrlEncoding()
    {
        return $this->configuration['clientUrlEncoding'];
    }
}
