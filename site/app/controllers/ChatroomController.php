<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\entities\chat\Chatroom;
use app\entities\chat\Message;
use app\libraries\routers\AccessControl;
use app\libraries\routers\Enabled;
use app\views\ChatroomView;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class ChatroomController extends AbstractController {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat", methods={"GET"})
     */
    public function showChatroomsPage(): WebResponse {
        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $user = $this->core->getUser();
        $chatrooms = $repo->findAll();
        $active_chatrooms = $repo->findAllActiveChatrooms();

        if ($user->accessAdmin()) {
            return new WebResponse(
                'Chatroom',
                'showChatPageInstructor',
                $chatrooms
            );
        }
        else { // Student view
            return new WebResponse(
                'Chatroom',
                'showChatPageStudent',
                $active_chatrooms
            );
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}", methods={"GET"})
     * @param string $chatroom_id
     * @return RedirectResponse|WebResponse
     */
     public function showChatroom(string $chatroom_id): WebResponse|RedirectResponse {
         if (!is_numeric($chatroom_id)) {
            $this->core->addErrorMessage("Invalid Chatroom ID");
            return new RedirectResponse($this->core->buildCourseUrl(['chat']));
        }

        $repo = $this->core->getCourseEntityManager()->getRepository(Chatroom::class);
        $chatroom = $repo->find($chatroom_id);

        return new WebResponse(
            'Chatroom',
            'showChatroom',
            $chatroom
        );
     }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/newChatroom", name="new_chatroom", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function addChatroom(): RedirectResponse {
        $em = $this->core->getCourseEntityManager();

        $chatroom = new Chatroom();
        $chatroom->setTitle($_POST['title']);
        $chatroom->setDescription($_POST['description']);
        $chatroom->setHostId($this->core->getUser()->getId());

        $em->persist($chatroom);
        $em->flush();

        $this->core->addSuccessMessage("Chatroom successfully added");
        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/delete", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function deleteChatroom(int $chatroom_id): JsonResponse {
        $chatroom_id = intval($_POST['chatroom_id'] ?? -1);
        $em = $this->core->getCourseEntityManager();
        $repo = $em->getRepository(Chatroom::class);

        $chatroom = $repo->find(Chatroom::class, $chatroom_id);
        if ($chatroom === null) {
            return JsonResponse::getFailResponse('Invalid Chatroom ID');
        }

        foreach ($chatroom->getMessages() as $message) {
            $em->remove($message);
        }
        $em->remove($chatroom);
        $em->flush();

        return JsonResponse::getSuccessResponse("Chatroom deleted successfully");
    }

/**
 * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/edit", name="edit_chatroom", methods={"POST"})
 * @AccessControl(role="INSTRUCTOR")
 */
public function editChatroom(int $chatroom_id): RedirectResponse {
    $em = $this->core->getCourseEntityManager();
    $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);

    if (!$chatroom) {
        $this->core->addErrorMessage("Chatroom not found");
        return new RedirectResponse($this->core->buildCourseUrl(['chat']));
    }

    if (isset($_POST['title'])) {
        $chatroom->setTitle($_POST['title']);
    }
    if (isset($_POST['description'])) {
        $chatroom->setDescription($_POST['description']);
    }

    $em->persist($chatroom);
    $em->flush();

    $this->core->addSuccessMessage("Chatroom successfully updated");
    return new RedirectResponse($this->core->buildCourseUrl(['chat']));
}

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/messages", name="fetch_chatroom_messages", methods={"GET"})
     */
    public function fetchMessages(int $chatroom_id): JsonResponse {
        $em = $this->core->getCourseEntityManager();
        $messages = $em->getRepository(Message::class)->findBy(['chatroom' => $chatroom_id], ['timestamp' => 'ASC']);

        $formattedMessages = array_map(function ($message) {
            return [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s'),
                'user_id' => $message->getUserId(),
                'display_name' => $message->getDisplayName()
            ];
        }, $messages);

        return JsonResponse::getSuccessResponse($formattedMessages);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/chat/{chatroom_id}/send", name="send_chatroom_messages", methods={"POST"})
     */
    public function sendMessage(int $chatroom_id): JsonResponse {
        $em = $this->core->getCourseEntityManager();
        $content = $_POST['content'] ?? '';
        $userId = $_POST['user_id'] ?? null;
        $displayName = $_POST['display_name'] ?? '';

        $chatroom = $em->getRepository(Chatroom::class)->find($chatroom_id);
        $message = new Message();
        $message->setChatroom($chatroom);
        $message->setUserId($userId);
        $message->setDisplayName($displayName);
        $message->setContent($content);
        $message->setTimestamp(new \DateTime("now"));

        $em->persist($message);
        $em->flush();

        return JsonResponse::getSuccessResponse($message);
    }
}
