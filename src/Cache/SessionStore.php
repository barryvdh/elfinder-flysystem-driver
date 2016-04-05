<?php

namespace Barryvdh\elFinderFlysystemDriver\Cache;

use elFinderSessionInterface;
use League\Flysystem\Cached\Storage\AbstractCache;

class SessionStore extends AbstractCache
{
    /**
     * @var elFinderSessionInterface The elFinder session
     */
    protected $session;

    /**
     * @var string storage key
     */
    protected $key;

    public function __construct(elFinderSessionInterface $session, $key)
    {
        $this->session = $session;
        $this->key = $key;
    }

    /**
     * Load the cache.
     */
    public function load()
    {
        $json = $this->session->get($this->key, null);

        if ($json !== null) {
            $this->setFromStorage($json);
        }
    }

    /**
     * Store the cache.
     */
    public function save()
    {
        $value = $this->getForStorage();

        $this->session->set($this->key, $value);
    }
}
