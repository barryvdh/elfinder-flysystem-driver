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

    /**
     * @var array options
     */
    protected $options;

    public function __construct(elFinderSessionInterface $session, $key, $options = array())
    {
        $this->session = $session;
        $this->key = $key;
        $this->options = $options;
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

    /**
     * Filter the contents from a listing.
     *
     * @param array $contents object listing
     *
     * @return array filtered contents
     */
    public function cleanContents(array $contents)
    {
        if (empty($this->options['hasDir'])) {
            return parent::cleanContents($contents);
        }
        $cachedProperties = array_flip([
            'path', 'dirname', 'basename', 'extension', 'filename',
            'size', 'mimetype', 'visibility', 'timestamp', 'type', 'hasdir'
        ]);

        foreach ($contents as $path => $object) {
            if (is_array($object)) {
                $contents[$path] = array_intersect_key($object, $cachedProperties);
            }
        }

        return $contents;
    }

    /**
     * Disabled Ensure parent directories of an object.
     *
     * @param string $path object path
     */
    public function ensureParentDirectories($path) {
        if (empty($this->options['disableEnsureParentDirectories'])) {
            return parent::ensureParentDirectories($path);
        }
    }

}
