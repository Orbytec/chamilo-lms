<?php
namespace Theodo\Evolution\Bundle\SessionBundle\Manager;

/**
 * This interface assures that proper session namespace handling
 */
interface BagManagerConfigurationInterface
{
    /**
     * Gets a list of all session namespaces used by application.
     * A session namespace is a key in $_SESSION array.
     *
     * @return array
     */
    public function getNamespaces();

    /**
     * Gets a session namespace from a namespace key.
     * A session namespace is a key in $_SESSION array.
     *
     * @param string $key
     * 
     * @return string
     */
    public function getNamespace($key);

    /**
     * Returns if the namespace is an array of namespaces.
     * Useful for nested session values.
     *
     * @param string $namespaceName
     *
     * @return boolean
     */
    public function isArray($namespaceName);
}
