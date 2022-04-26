<?php

namespace Classes;

class Site {

    private $app = null;

    public function __construct() {
        $this->app = require __DIR__.'/../app.php';
    }

    /**
     * Method login a customer using credentials
     * 
     * @param string $username
     * @param string $password
     * 
     * @return user array on success, empty array on failure
     */
    public function login(?string $username, ?string $password) : array {

        if (empty($username) || empty($password)) {
            return [];
        }
        
        $user = $this->app['db']->fetchAssoc("
            SELECT `id`, `username`
            FROM `users` 
            WHERE `username` = ? 
            AND `password` = ?
        ", [$username, md5($password)]);

        return $user ?: [];
    }

}

?>