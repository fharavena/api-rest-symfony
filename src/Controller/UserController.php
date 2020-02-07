<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

use App\Entity\User;
use App\Entity\Video;

class UserController extends AbstractController
{
    private function resjson($data)
    {
        // Serializar datos con servicio de serializer
        $json = $this->get('serializer')->serialize($data, 'json');

        // response con httpfoundation
        $response = new Response();

        // asignar contenido a la respuesta
        $response->setContent($json);

        // indicar formato de respuesta
        $response->headers->set('Content-Type', 'application/json');

        // devolver la respuesta
        return $response;
    }


    public function index()
    {
        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $video_repo = $this->getDoctrine()->getRepository(Video::class);

        $users = $user_repo->findAll();
        $user = $user_repo->find(1);
        $videos = $video_repo->findAll();


        $data = [
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ];

        // foreach($users as $user){
        //     echo "<h1>{$user->getName()} {$user->getSurname()}</h1>";
        //     foreach ($user->getVideos() as $video) {
        //         echo "<p> {$video->getTitle()} - {$video->getUser()->getEmail()}</p>";

        //     }
        // }

        return $this->resjson($data);
    }

    public function create(Request $request)
    {
        // recoger los datos por post
        $json = $request->get('json', null);

        // decodificar el json
        $params  = json_decode($json);

        //respuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha creado.'
        ];

        // comprobar y validar datos
        if ($json != null) {
            $name = (!empty($params->name)) ? $params->name : null;
            $surname = (!empty($params->surname)) ? $params->surname : null;
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if (!empty($email) && count($validate_email) == 0 && !empty($password)  && !empty($name) && !empty($surname)) {
                // si la validacion es correcta, crear el objeto de usuario
                $user = new User();
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new \DateTime('now'));

                // cifrar la contraseña
                $pwd = hash('sha256', $password);
                $user->setPassword($pwd);

                // comprobar si el usuario existe (control de duplicados)
                $doctrine = $this->getDoctrine();
                $em = $doctrine->getManager();

                $user_repo = $doctrine->getRepository(User::class);
                $isset_user = $user_repo->findBy(array(
                    'email' => $email
                ));

                if (count($isset_user) == 0) {
                    //guardo el usuario
                    $em->persist($user);
                    $em->flush();

                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'El usuario ha sido creado correctamente.',
                        'user' => $user
                    ];
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'El usuario ya existe.'
                    ];
                }

                //si no existe, guardarlo en la base de datos
            }
        }

        // hacer respuesta en json
        return $this->resjson($data);
    }
}
