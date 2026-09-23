<?php
declare(strict_types=1);


namespace App\Controllers;

use App\Core\View;
use App\Repositories\UserRepository;

class HomeController {

    protected View $view;
    protected UserRepository $userRepository;


    /**
     * HomeController constructor.
     *
     * @param View $view View engine for rendering templates
     * @param UserRepository $userRepository Repository for user data
     */
    public function __construct(View $view, UserRepository $userRepository)
    {
        $this->view = $view;
        $this->userRepository = $userRepository;
        $this->view->layout('layouts.main');
    }

    /**
     * Renders the home page with a list of users.
     *
     * @return mixed Rendered view content
     */
    public function index(): mixed
    {
        
        $users = (array)$this->userRepository->all();

        return $this->view->render('home', [
            'title' => 'Welcome to My Framework',
            'users' => $users
        ]);
    }
}