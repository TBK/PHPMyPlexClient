<?php
/*
 * The MIT License
 *
 * Copyright 2015 Chris Stretton <cstretton@gmail.com>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace PHPMyPlex\Containers;

use PHPMyPlex\Exceptions as Exceptions;
use PHPMyPlex;

/**
 * Parses the response from plex and manages children and attributes of a Plex Media Container.
 *
 * @author Chris Stretton <cstretton@gmail.com>
 */
class MediaContainer
{

    protected static $methodAliases = [
        'loadAll' => 'load',
        'show' => 'child',
        'season' => 'child',
        'episode' => 'child',
        'movie' => 'child',
        'shows' => 'children',
        'seasons' => 'children',
        'episodes' => 'children',
        'movies' => 'children'
    ];
    
    protected $details;
    protected $xml;
    protected $server;

    /**
     * Creates a new Media Container from a provided container object and parses the attributes
     * into accessible members.
     * Takes a reference to the plex server that spawned the data for loading.
     * 
     * @param \SimpleXMLElement $data
     * @param PHPMyPlex\PlexServer $server
     */
    public function __construct(\SimpleXMLElement $data, PHPMyPlex\PlexServer $server)
    {
        $this->xml = $data;
        $this->server = $server;
        $this->details = $this->parseMediaContainer($data);
    }
    
    /**
     * Determines if there are any children within the current container.
     * 
     * @return boolean
     */
    public function hasChildren()
    {
        return $this->xml->count() > 0;
    }

    /**
     * Returns a collection of MEdiaContainers for all of the children below
     * the current container. If a title is given then the children are refined
     * to only that title.
     * 
     * @param boolean|string $title = false
     * @return \PHPMyPlex\Containers\MediaContainerCollection
     * @throws Exceptions\MyPlexDataException
     */
    public function children($title = false)
    {
        $children = [];
        if ($this->hasChildren()) {

            if ($title) {
                $nodes = $this->xml->xpath('//*[translate(@title, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . strtolower($title) . '"]');
            } else {
                $nodes = $this->xml->children();
            }

            foreach ($nodes as $child) {
                $name = __NAMESPACE__ . '\\' . $child->getName();
                if (\class_exists($name)) {
                    $children[] = new $name($child, $this->server);
                } else {
                    throw new Exceptions\MyPlexDataException("No handler exists for container {$name}");
                }
            }
        }
        return new MediaContainerCollection($children);
    }

    /**
     * Gets s single media container for a given child by title. Returns false
     * if the child is not found.
     * 
     * @param string $title
     * @return MediaContainerCollection|boolean
     */
    public function child($title)
    {
        $children = $this->children($title);
        if (count($children) > 0) {
            return $children[0];
        }
        return false;
    }

    /**
     * checks if the current container has a key
     * @return boolean
     */
    public function hasKey()
    {
        return \array_key_exists('key', $this->details);
    }

    /**
     * Returns the current containers details as an associative array.
     * @return array
     */
    public function getDetails()
    {
        return $this->details->getArrayCopy();
    }

    /**
     * Goes back to the plex server and loads the current item's key to provide
     * more in-depth details about the level and its children. Returns itself for chaining.
     * 
     * @return \PHPMyPlex\Containers\MediaContainer
     */
    public function load()
    {
        if ($this->hasKey()) {
            $this->xml = $this->server->loadContainerRaw($this->key);
            $this->parseMediaContainer($this->xml);
        }
        return $this;
    }

    /**
     * Allows aliasing of children(), child() and load() calls for semantic calling
     * by users of the objects.
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (\array_key_exists($name, self::$methodAliases))
        {
            return \call_user_func_array([$this, self::$methodAliases[$name]], $arguments);
        }
    }
    
    /**
     * Helper method to return the current title or node name of the media container
     * @return string
     */
    public function __toString()
    {
        if (\array_key_exists('title', $this->details)) {
            return $this->details['title'];
        } else {
            return $this->xml->getName();
        }
    }
    
    /**
     * Helper method to allow direct access of detail struct properties as members.
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (\array_key_exists($name, $this->details)) {
            return $this->details[$name];
        }
    }

    /**
     * Takes the details of the plex server and parses it into
     * the detail struct.
     * @param \SimpleXMLElement $node
     * @return \PHPMyPlex\Containers\ContainerDetails
     */
    protected function parseMediaContainer(\SimpleXMLElement $node)
    {
        $data = [];

        foreach ($node->attributes() as $attributeKey => $attributeValue) {
            $data[$attributeKey] = (string) $attributeValue;
        }

        return new ContainerDetails($data);
    }
}