<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Models\Role;
use App\Models\User;

class AdminController extends Controller
{
    protected View $view;

    /**
     * AdminController constructor.
     *
     * @param View $view View engine for rendering web templates
     */
    public function __construct(View $view) {
        $this->view = $view;
        $this->view->layout('layouts.main');
        
        /* if(!isLoggedIn()){
            redirect('/web/login');
        } */
    }
    /**
     * Renders the admin dashboard.
     *
     * @return mixed Rendered view content
     */
    public function index(): mixed
    {
        return $this->view->render('admin/index', [
            'title' => 'Welcome to My Framework',
        ]);
    }

    /**
     * Renders the users management page.
     *
     * @return mixed Rendered view content
     */
    public function users(): mixed
    {
        $users = (array)User::all();
        $roles = (array)Role::all();

        return $this->view->render('admin/users', [
            'errors' => [],
            'users' => $users,
            'roles' => $roles
        ]);
    }

    /**
     * Renders the user creation page.
     *
     * @return mixed Rendered view content
     */
    public function createUser(): mixed
    {
        $roles = (array)User::getRoles();

        return view('admin/create_user', [
            'roles' => $roles
        ]);
    }

    /**
     * Renders the students management page.
     *
     * @return mixed Rendered view content
     */
    public function students(): mixed
    {        
        return view('admin/students');
    }

    /**
     * Renders the roles management page.
     *
     * @return mixed Rendered view content
     */
    public function roles(): mixed
    {
        $roles = (array)Role::all();

        return view('admin/roles', [
            'roles' => $roles
        ]);
    }

    /**
     * Renders the role creation page.
     *
     * @return mixed Rendered view content
     */
    public function createRole(): mixed
    {
        return view('admin/create_role', [
            'permissions' => []
        ]);
    }

    /**
     * Renders the permissions management page.
     *
     * @return mixed Rendered view content
     */
    public function permissions(): mixed
    {
        return view('admin/permissions', [
            'permissions' => []
        ]);
    }

    /**
     * Renders the permission creation page.
     *
     * @return mixed Rendered view content
     */
    public function createPermission(): mixed
    {
        return view('admin/create_permission', [
            'permissions' => []
        ]);
    }

    

}
