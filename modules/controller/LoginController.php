<?php


class LoginController
{
    /**
     * Affiche la vue de la page login.
     *
     * @return void
     */
    public function display()
    {
        ViewHandler::show("LoginView");
    }

}