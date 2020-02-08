<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use Knp\Component\Pager\PaginatorInterface;

use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;
use PhpParser\Node\Expr\BinaryOp\Identical;

class VideoController extends AbstractController
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
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }

    public function create(Request $request, JwtAuth $jwt_auth, $id = null)
    {
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'El video no ha podido crearse'
        ];
        // recoger el token
        $token = $request->headers->get('Authorization', null);

        // comprobar si es correcto
        $authCheck = $jwt_auth->checkToken($token);

        if ($authCheck) {
            // recoger datos por post
            $json = $request->get('json', null);
            $params = json_decode($json);

            // recoger el objeto del usuario identificado
            $identity = $jwt_auth->checkToken($token, true);

            // comprobar y validardatos
            if (!empty($json)) {
                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = (!empty($params->title)) ? $params->title : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $url = (!empty($params->url)) ? $params->url : null;

                if (!empty($user_id) && !empty($title)) {
                    // guardar el nuevo video favorito en la bd
                    $em = $this->getDoctrine()->getManager();
                    $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                        'id' => $user_id
                    ]);

                    if ($id == null) {
                        // Crear y guardar objeto
                        $video = new Video();
                        $video->setUser($user);
                        $video->setTitle($title);
                        $video->setDescription($description);
                        $video->setUrl($url);
                        $video->setStatus('normal');

                        $createdAt = new \DateTime('now');
                        $updatedAt = new \DateTime('now');
                        $video->setCreatedAt($createdAt);
                        $video->setUpdatedAt($updatedAt);

                        // guardar datos
                        $em->persist($video);
                        $em->flush();
                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El video se ha guardado',
                            'video' => $video
                        ];
                    } else {
                        $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                            'id' => $id,
                            'user' => $identity->sub
                        ]);

                        if ($video && is_object($video)) {
                            $video->setTitle($title);
                            $video->setDescription($description);
                            $video->setUrl($url);
                            $updatedAt = new \DateTime('now');
                            $video->setUpdatedAt($updatedAt);

                            // guardar datos
                            $em->persist($video);
                            $em->flush();
                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'El video se ha actualizado',
                                'video' => $video
                            ];
                        }
                    }
                }
            }
        }


        // devolver una respuesta

        return $this->resjson($data);
    }

    public function videos(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator)
    {
        // recoger la cabecera de autenticacion
        $token = $request->headers->get('Authorization');

        // comrobar el token
        $authCheck = $jwt_auth->checkToken($token);

        // si es valido
        if ($authCheck) {
            // conseguir la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            $em = $this->getDoctrine()->getManager();

            // hacer una consulta para paginar
            $dql = "SELECT v FROM App\Entity\Video v where v.user = {$identity->sub} ORDER BY v.id DESC";
            $query = $em->createQuery($dql);


            // recoger el parametro page de la url
            $page = $request->query->getInt('page', 1);
            $items_per_page = 5;

            // invocar paginacion
            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();

            // preparar array de datos para devolver            
            $data = array(
                'status' => 'success',
                'code' => 200,
                'total_items_counts' => $total,
                'page_actual' => $page,
                'items_per_page' => $items_per_page,
                'total_pages' => ceil($total / $items_per_page),
                'videos' => $pagination,
                'user' => $identity->sub
            );
        } else {
            // si falla delver esto
            $data = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'No se pueden listar los videos en este momento'
            );
        }

        return $this->resjson($data);
    }

    public function video(Request $request, JwtAuth $jwt_auth, $id = null)
    {
        // sacar el token y comprobar si es correcto
        $token = $request->headers->get('Authorization');
        $authCheck = $jwt_auth->checkToken($token);

        // devolver una respuesta
        $data = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'Video no encontrado'
        );

        if ($authCheck) {
            // sacar la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            // sacar el objeto del video en base al id
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);

            // comprobar si el video existe y es propiedad del usuario identificado
            if ($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                $data = array(
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                );
            }
        }

        return $this->resjson($data);
    }

    public function remove(Request $request, JwtAuth $jwt_auth, $id = null)
    {
        // sacar el token y comprobar si es correcto
        $token = $request->headers->get('Authorization');
        $authCheck = $jwt_auth->checkToken($token);

        // devolver una respuesta
        $data = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'Video no encontrado'
        );

        if ($authCheck) {
            // sacar la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            // sacar el objeto del video en base al id
            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();
            $video = $doctrine->getRepository(Video::class)->findOneBy(['id' => $id]);

            if ($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                $em->remove($video);
                $em->flush();

                $data = array(
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                );
            }
        }

        return $this->resjson($data);
    }
}
