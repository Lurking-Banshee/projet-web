<?php

namespace App\Controllers;

use App\Models\Compagnies;
use App\Models\Companies;
use App\Models\Creators;
use App\Models\Genre;
use App\Models\Genres;
use App\Models\Seasons;
use App\Models\Series;
use phpDocumentor\Reflection\Types\Array_;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\User;

final class HomeController
{
    private $view;
    private $logger;
    private $user;

    public function __construct($c)
    {
        $this->view = $c->get('view');
        $this->logger = $c->get('logger');
        $this->model = $c->get('App\Repositories\UserRepository');
        $this->router = $c->get('router');
    }

    public function dispatch(Request $request, Response $response, $args)
    {
        $this->logger->info("Home page action dispatched");
        $tabNouv = Series::orderBy('first_air_date', 'DESC')->take(4)->get();
        $tabTend = Series::orderBy('popularity', 'DESC')->take(4)->get();
        $this->view->render($response, 'homepage.twig', array('seriesNouv' => $tabNouv, 'seriesTend' => $tabTend));
        return $response;
    }

    public function signup(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'signup.twig');
    }

    public function signin(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'signin.twig');
    }

    public function show(Request $request, Response $response, $args)
    {
        $serie = Series::find($args['id']);
        $tabSaison = $serie->saisons()->orderBy('air_date','ASC')->get();
        foreach ($tabSaison as $season ){
            $tabEpisodes = $season->episodes()->orderBy('number','ASC')->get();
            $season['tabEpisodes'] = $tabEpisodes;
        }
        return $this->view->render($response, 'show.twig', Array("serie"=>$serie,"seasons"=>$tabSaison));
    }

    public function search(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'search.twig',Array("type"=>$args['id']));
    }

    public function profile(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'profile.twig');
    }

    public function addUser(Request $request, Response $response, $args)
    {
        if (isset($_POST['username']) && isset($_POST['email']) && isset($_POST['password'])) {

            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = $_POST['password'];

            $errors = array();

            if ($username != filter_var($username, FILTER_SANITIZE_STRING)) {
                array_push($errors, "Nom invalide, merci de corriger");
            }
            if ($email != filter_var($email, FILTER_VALIDATE_EMAIL)) {
                array_push($errors, "Adresse email invalide, merci de corriger");
            } else {
                $emailVerif = \App\Models\User::where('email', $email)->get();
                if (sizeof($emailVerif) != 0) {
                    array_push($errors, "Un compte a déjà été créé avec cette adresse email ou ce pseudo");
                }
            }
            if ($password != filter_var($password, FILTER_SANITIZE_STRING)) {
                array_push($errors, "Mot de passe invalide, merci de corriger");
            }

            if (sizeof($errors) == 0) {
                $username = filter_var($username, FILTER_SANITIZE_STRING);
                $email = filter_var($email, FILTER_SANITIZE_EMAIL);
                $password = password_hash($password, PASSWORD_DEFAULT, Array(
                    'cost' => 12
                ));

                $user = new \App\Models\User();
                $user->name = $username;
                $user->email = $email;
                $user->password = $password;
                $user->save();

                $_SESSION['uniqid'] = $user->id;
                $_SESSION['type'] = 'user';

                if (isset($_SESSION['route'])) {
                    $derniere_route = $_SESSION['route'];
                    unset($_SESSION['route']);
                    return $response->withStatus(302)->withHeader('Location', $derniere_route);
                } else {
                    return $response->withRedirect($this->router->pathFor('homepage'));
                }

            } else {
                return $this->view->render($response, 'signup.twig', array('errors' => $errors));

            }
        } else {
            return $response->withRedirect($this->router->pathFor('homepage'));

        }
    }

    public function resultSearch(Request $request, Response $response, $args)
    {
        $tabSeries = Array();

        if (isset($_POST['genre'])) {
            $genre = Genres::find($_POST['genre']);
            $tabSeries['genre'] = $genre[0]->series()->get();
            return $this->view->render($response, 'resultSearch.twig', Array("series" => $tabSeries['genre']));
        }

        if (isset($_POST['company'])) {
            $compagny = Companies::where('name', $_POST['company'])->get();
            $tabSeries[$compagny->name] = $compagny[0]->series()->get();
            return $this->view->render($response, 'resultSearch.twig', Array("series" => $tabSeries[$compagny->name]));
        }

        if (isset($_POST['creator'])) {
            $creator = Creators::where('name', $_POST['creator'])->get();
            $tabSeries[$creator->name] = $creator[0]->series()->get();
            return $this->view->render($response, 'resultSearch.twig', Array("series" => $tabSeries[$creator->name]));
        }

        if (isset($_POST['name'])) {
            $serie = Series::where('name', $_POST['name'])->get();

            return $this->view->render($response, 'resultSearch.twig', Array("series" => $serie));
        }
    }
    public function loginUser(Request $request, Response $response, $args)
    {
        if (isset($_POST["email"]) && isset($_POST["password"])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_STRING);
            $password = filter_var($_POST['password'], FILTER_SANITIZE_STRING);
            $user = User::where("email", $email)->get()->first();
            //var_dump($user);
            if (isset($user->id)) {
                if (password_verify($password, $user->password)) {
                    $_SESSION["uniqid"] = $user->id;
                    $_SESSION["type"] = 'user';
                    var_dump($_SESSION['uniqid']);
                    return $response->withRedirect($this->router->pathFor('homepage'));

                } else {
                    $this->view->render($response, 'signin.twig', array('errors' => "error"));
                }
            } else {
                $this->view->render($response, 'signin.twig', array('errors' => "error"));
            }
        } else {
            $this->view->render($response, 'signin.twig', array('errors' => "error"));
        }
    }

    public function logout(Request $request, Response $response, $args)
    {
        unset($_SESSION['uniqid']);
        return $response->withRedirect($this->router->pathFor('homepage'));
    }
}
