<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use \Classes\Site;

const PAGE_SIZE = 3;

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));
    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});


$app->match('/login', function (Request $request) use ($app) {

    $username = $request->get('username');
    $password = $request->get('password');

    if ($username) {
        
        $site = new Site;

        // try to login
        if ($user = $site->login($username, $password)){
            $app['session']->set('user', $user);
            // only redirect on success
            return $app->redirect('/todo');
        }
    }

    return $app['twig']->render('login.html', array());

});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});


/** This endpoint only acts like a router, data will be rendered by vue */
$app->get('/todo/{id}', function (Request $request, $id) use ($app) {
    return $app['twig']->render('todos.html', []);
})->value('id', null);


/** API for adding a new todo */
$app->post('/todo/add', function (Request $request) use ($app) {

    $returnData = [
        'status' => false,
        'error' => null,
    ];

    if (null === $user = $app['session']->get('user')) {
        // UX error handling
        $returnData['error'] = 'Oops, please re-login first to add a todo.';
        return $app->json($returnData);
    }

    // exception handle 
    // in case $user['id'] is undefined
    if (empty($user_id = ($user['id'] ?? null))) {
        $returnData['error'] = 'Oops, Can not find your user id, please re-login.';
        return $app->json($returnData);
    };

    // can't post if empty
    if (empty($description = $request->get('description'))) {
        $returnData['error'] = 'Please provide a description.';
        return $app->json($returnData);
    };

    // verify crsf first, redirect with error
    if ($app['session']->get('crsftokentodos') != $request->get('crsftokentodos')) {
        $returnData['error'] = 'Please reload the page and try again.';
        return $app->json($returnData);
    }

    // prepare statement to insert
    $affectedrows = $app['db']->executeUpdate("
        INSERT INTO `todos`
        (`user_id`, `description`) 
        VALUES (?, ?)
    ", [$user_id, $description]);
    
    // success
    if ($affectedrows) {

        // on success 
        // only get the last inserted data for performance reason
        $lastInsert = $app['db']->fetchAssoc("
            SELECT *
            FROM `todos`
            WHERE `user_id` = ?
            AND `id` = ?
        ", [$user['id'] ?? null, $app['db']->lastInsertId()]);

        return $app->json([
            'status' => true,
            'success' => 'Todo has been added successfully.',
            // send last inserted data to update the DOM
            'lastinsert' => $lastInsert
        ]);
    }

    $returnData['error'] = 'Something has gnoe wrong, please try again.';
    return $app->json($returnData);
});


/** API for deleting the todo */
$app->delete('/todo/delete/{id}', function ($id) use ($app) {

    // check if user is logged in
    if (null === $user = $app['session']->get('user')) {
        // UX error handling
        return $app->json([
            'status' => false,
            'error' => 'Oops, please re-login first to delete a todo.',
        ]);
    }

    // 1. prepare statement to prevent sql injection
    // 2. in case user['id'] is somehow undefined
    // 3. can only delete a todo if its the author
    $affectedrows = $app['db']->executeUpdate("
        DELETE FROM `todos`
        WHERE `id` = ?
        AND `user_id` = ?
    ", [$id, ($user['id'] ?? null)]);

    // success
    if ($affectedrows) {
        return $app->json([
            'status' => true,
            'success' => 'Todo has been deleted successfully.',
        ]);
    }

    // else error
    return $app->json([
        'status' => false,
        'error' => 'Oops, something has gone wrong, please try again.',
    ]);
});

/** API for patching the todo's complete data */
$app->patch('/todo/togglecomplete/{id}', function ($id) use ($app) {

    // check if user is logged in
    if (null === $user = $app['session']->get('user')) {
        // UX error handling
        return $app->json([
            'status' => false,
            'error' => 'Oops, please re-login first to mark a todo.',
        ]);
    }

    // 1. prepare statement to prevent sql injection
    // 2. only allow user to mark/unmark their own post to be completed
    $affectedrows = $app['db']->executeUpdate("
        UPDATE `todos`
        SET `completed` = 1 - `completed`
        WHERE `id` = ?
        AND `user_id` = ?
    ", [$id, $user['id']]);

    // success
    if ($affectedrows) {
        return $app->json([
            'status' => true,
            'success' => 'Todo has been marked successfully.',
        ]);
    }

    // else error
    return $app->json([
        'status' => false,
        'error' => 'Oops, something has gone wrong, please try again.',
    ]);

});

/** API for fetching the initial todo data */
$app->get('/new/todo/{id}', function ($id) use ($app) {

    if (null === $user = $app['session']->get('user')) {
        return $app->json([]);
    }

    if ($id){

        // prepare statement to prevent sql injection & fetch record
        $todo = $app['db']->fetchAssoc("
            SELECT *
            FROM `todos`
            WHERE `id` = ?
        ", [$id]);

        // return with json
        return $app['twig']->render('todo.html', [
            'todo' => $todo,
        ]);

    } else {

        // create a crsf token for todos
        $token = md5(uniqid(mt_rand(), true));
        // save into session
        $app['session']->set('crsftokentodos', $token);

        // 1. prepare statement to prevent sql injection & fetch record
        // 2. handle exception - if $user['id'] is undefined for some reason, see no post
        $todos = $app['db']->fetchAll("
            SELECT *
            FROM `todos`
            WHERE `user_id` = ?
        ", [$user['id'] ?? null]);

        // return with json
        return $app->json([
            'todos' => $todos,
            'form' => [
                'crsftokentodos' => $token,
                'description' => null
            ]
        ]);
    }
});