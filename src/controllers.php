<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    if (empty($username) || empty($password)) {

        // create a crsf token
        $token = md5(uniqid(mt_rand(), true));
        // save into session
        $app['session']->set('crsftokenlogin', $token);
        
        // inject the token into the data to be used by the form
        return $app['twig']->render('login.html', [
            'crsftokenlogin' => $token
        ]);
    }
    
    // verify crsf first, redirect with error
    if ($app['session']->get('crsftokenlogin') != $request->get('token')) {
        $app['session']->getFlashBag()->set('loginError', 'Please reload the page and try again.');
        return $app->redirect('/login');
    }

    // 1. only select the columns needed for query performance (dont save password in the session)
    // 2. prepare statement to prevent sql injection & fetch record
    // 3. hashed password was stored in db for security reasons
    $user = $app['db']->fetchAssoc("
        SELECT `id`, `username`
        FROM `users` 
        WHERE `username` = ? 
        AND `password` = ?
    ", [$username, md5($password)]);

    // only redirect on success
    if ($user){
        $app['session']->set('user', $user);
        return $app->redirect('/todo');
    }

    // error
    $app['session']->getFlashBag()->set('loginError', 'Cant find the user, Please try again.');
    return $app->redirect('/login');

});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});


$app->get('/todo/{id}', function ($id) use ($app) {

    if (null === $user = $app['session']->get('user')) {
        // UX error handling
        $app['session']->getFlashBag()->set('loginError', 'Oops, please login first to view the list.');
        return $app->redirect('/login');
    }

    if ($id){

        // prepare statement to prevent sql injection & fetch record
        $todo = $app['db']->fetchAssoc("
            SELECT *
            FROM `todos`
            WHERE `id` = ?
        ", [$id]);

        return $app['twig']->render('todo.html', [
            'todo' => $todo,
        ]);

    } else {

        // create a crsf token
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

        return $app['twig']->render('todos.html', [
            'todos' => $todos,
            'crsftokentodos' => $token
        ]);
    }
})
->value('id', null);


$app->post('/todo/add', function (Request $request) use ($app) {

    if (null === $user = $app['session']->get('user')) {
        // UX error handling
        $app['session']->getFlashBag()->set('loginError', 'Oops, please login first to view the list.');
        return $app->redirect('/login');
    }

    // exception handle 
    // in case $user['id'] is undefined
    if (empty($user_id = ($user['id'] ?? null))) {
        $app['session']->getFlashBag()->set('loginError', 'Oops, Can not find your user id, please re-login.');
        return $app->redirect('/login');
    };

    // can't post if empty
    if (empty($description = $request->get('description'))) {
        $errors = [
            'description' => 'Please provide a description.',
        ];
    };

    // verify crsf first, redirect with error
    if ($app['session']->get('crsftokentodos') != $request->get('token')) {
        $errors = [
            'token' => 'Please reload the page and try again.'
        ];
    }

    // more errors etc...

    // if any errors are encountered - redirect
    if (!empty($errors ?? [])) {
        $app['session']->getFlashBag()->set('formErrors', $errors);
        return $app->redirect('/todo');
    }

    // prepare statement to insert
    $app['db']->executeUpdate("
        INSERT INTO `todos`
        (`user_id`, `description`) 
        VALUES (?, ?)
    ", [$user_id, $description]);

    // add success message when to do is added
    $app['session']->getFlashBag()->set('todoSuccess', 'Todo has been added successfully.');
    return $app->redirect('/todo');
});


$app->match('/todo/delete/{id}', function ($id) use ($app) {

    // check if user is logged in
    if (null === $user = $app['session']->get('user')) {
        // UX error handling
        $app['session']->getFlashBag()->set('loginError', 'Oops, please login first to delete a todo.');
        return $app->redirect('/login');
    }

    // 1. prepare statement to prevent sql injection
    // 2. in case user['id'] is somehow undefined
    // 3. can only delete a todo if its the author
    $affectedrows = $app['db']->executeUpdate("
        DELETE FROM `todos`
        WHERE `id` = ?
        AND `user_id` = ?
    ", [$id, ($user['id'] ?? null)]);

    // add success message when to do is deleted
    $app['session']->getFlashBag()->set('todoSuccess', 'Todo has been deleted successfully.');
    return $app->redirect('/todo');
});

$app->post('/todo/togglecomplete/{id}', function ($id) use ($app) {

    // check if user is logged in
    if (null === $user = $app['session']->get('user')) {
        // UX error handling
        $app['session']->getFlashBag()->set('loginError', 'Oops, please login first to mark a todo.');
        return $app->redirect('/login');
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
        $app['session']->getFlashBag()->set('todoSuccess', 'Todo has been marked successfully.');
        return $app->redirect('/todo');
    }

    // else error
    $app['session']->getFlashBag()->set('formErrors', 'Oops, something has gone wrong, please try again.');
    return $app->redirect('/todo');

});

$app->get('/todo/{id}/json', function ($id) use ($app) {
    
    // check if user is logged in
    if (null === $user = $app['session']->get('user')) {
       // UX error handling
       $app['session']->getFlashBag()->set('loginError', 'Oops, please login first to view json.');
       return $app->redirect('/login');
   }

   // 1. prepare statement to prevent sql injection
   // 2. only allow user to view their own todo in json
   $todo = $app['db']->fetchAssoc("
       SELECT `id`, `user_id`, `description`
       FROM `todos`
       WHERE `id` = ?
       AND `user_id` = ?
   ", [$id, $user['id']]);

   return $app->json($todo ?: 'no todo has been found');
});