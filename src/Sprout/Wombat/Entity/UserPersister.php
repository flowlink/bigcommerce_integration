<?php

use Sprout\Wombat\Entity\User;

namespace Sprout\Wombat\Entity;

/**
 * Set up basic file persistence
 */

class UserPersister
{
    private $basePath;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    public function persist(User $info)
    {
        $data = $info->getAttributes();
        $json = json_encode($data);
        $filename = $this->basePath.'/user.json';
        file_put_contents($filename, $json, LOCK_EX);
    }

    public function retrieve()  {
    	$json = file_get_contents($this->basePath.'/user.json');
    	$attributes = json_decode($json,true);
        
    	return new User($attributes);
    }
}