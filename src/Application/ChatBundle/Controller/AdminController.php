<?php

namespace Application\ChatBundle\Controller;

use Application\ChatBundle\Document\Operator\Department;
use Doctrine\ODM\MongoDB\Mapping\Document;
use Application\ChatBundle\Form\OperatorDepartmentForm;
use Application\ChatBundle\Form\OperatorForm;
use Symfony\Component\Security\Exception\UsernameNotFoundException;
use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Form\PasswordField;
use Symfony\Component\Form\TextField;
use Application\ChatBundle\Document\Operator;
use Symfony\Component\Form\Form;
use Application\ChatBundle\Controller\BaseController;
use Application\ChatBundle\Document\Session as ChatSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Description of AdminController
 *
 * @author Ismael Ambrosi<ismael@servergrove.com>
 */
class AdminController extends BaseController
{

    private function createLoginForm($operator = null)
    {
        $form = new Form('login', $operator, $this->get('validator'));
        $form->add(new TextField('email'));
        $form->add(new PasswordField('passwd'));

        return $form;
    }

    private function isLogged()
    {
        return $this->getHttpSession()->get('_operator');
    }

    private function checkLogin()
    {
        if (!$this->isLogged()) {
            return $this->forward('ChatBundle:Admin:login');
        }

        $operator = $this->getOperator();
        $operator->setIsOnline(true);
        $this->getDocumentManager()->persist($operator);
        $this->getDocumentManager()->flush();

        return null;
    }

    /**
     * @todo Search about security in Symfony2
     */
    public function checkLoginAction()
    {
        $form = $this->createLoginForm(new Operator());
        $form->bind($this->get('request')->request->get('login'));

        if (!$form->isValid()) {

            return $this->redirect($this->generateUrl("_security_login", array(
                        'e' => __LINE__)));
        }
        try {
            /* @var $operator Application\ChatBundle\Document\Operator */
            $operator = $this->getDocumentManager()->getRepository('ChatBundle:Operator')->loadUserByUsername($form->get('email')->getDisplayedData());
            if (!$operator->encodePassword($form->get('passwd')->getDisplayedData(), $operator->getSalt())) {
                throw new UsernameNotFoundException('Invalid password');
            }

            $this->getHttpSession()->set('_operator', $operator->getId());
            $operator->setIsOnline(true);
            $this->getDocumentManager()->persist($operator);
            $this->getDocumentManager()->flush();
        } catch (UsernameNotFoundException $e) {
            $this->getHttpSession()->setFlash('_error', $e->getMessage());
            return $this->redirect($this->generateUrl("_security_login", array(
                        'e' => __LINE__)));
        }

        return $this->redirect($this->generateUrl("sglc_admin_index"));
    }

    public function indexAction()
    {
        if (!is_null($response = $this->checkLogin())) {
            return $response;
        }

        return $this->redirect($this->generateUrl('sglc_admin_console_sessions'));
    }

    public function loginAction()
    {
        $errorMsg = $this->getHttpSession()->getFlash('_error');
        $form = $this->createLoginForm();

        return $this->renderTemplate('ChatBundle:Admin:login.twig.html', array(
            'form' => $form,
            'errorMsg' => $errorMsg));
    }

    public function logoutAction()
    {
        if ($this->isLogged()) {
            $operator = $this->getOperator();
            $operator->setIsOnline(false);
            $this->getDocumentManager()->persist($operator);
            $this->getDocumentManager()->flush();
        }

        if (!is_null($response = $this->checkLogin())) {
            return $response;
        }
        return $this->redirect($this->generateUrl("_security_login"));
    }

    private function getRequestedChats()
    {
        return $this->getDocumentManager()->getRepository('ChatBundle:Session')->getRequestedChats();
    }

    private function getRequestedChatsArray()
    {
        return $this->getDocumentManager()->getRepository('ChatBundle:Session')->getRequestedChatsArray();
    }

    public function sessionsAction()
    {
        if (!is_null($response = $this->checkLogin())) {
            return $response;
        }

        $this->getDocumentManager()->getRepository('ChatBundle:Session')->closeSessions();

        return $this->renderTemplate('ChatBundle:Admin:requests.twig.html', array(
            'chats' => $this->getRequestedChats()));
    }

    public function sessionsApiAction($_format)
    {
        return $this->renderTemplate('ChatBundle:Admin:Sessions.twig.' . $_format);
    }

    public function sessionsServiceAction()
    {
        if (!is_null($response = $this->checkLogin())) {
            $this->getResponse()->setStatusCode(401);
            $this->getResponse()->setContent('');
            return $this->getResponse();
        }

        $this->getDocumentManager()->getRepository('ChatBundle:Session')->closeSessions();

        $this->getResponse()->headers->set('Content-type', 'application/json');

        $json = array();
        $json['requests'] = $this->getRequestedChatsArray();
        $json['count']['requests'] = count($json['requests']);
        $json['visits'] = $this->getDocumentManager()->getRepository('ChatBundle:Visit')->getLastVisitsArray();
        $json['count']['visits'] = count($json['visits']);
        $json['count']['online_operators'] = $this->getDocumentManager()->getRepository('ChatBundle:Operator')->getOnlineOperatorsCount();

        $this->getResponse()->setContent(json_encode($json));

        return $this->getResponse();
    }

    public function requestedChatsAction($_format)
    {
        if (!is_null($response = $this->checkLogin())) {
            $this->getResponse()->setStatusCode(401);
            $this->getResponse()->setContent('');
            return $this->getResponse();
        }

        $this->getDocumentManager()->getRepository('ChatBundle:Session')->closeSessions();

        if ($_format == 'json') {
            $this->getResponse()->headers->set('Content-type', 'application/json');
            $this->getResponse()->setContent(json_encode($this->getRequestedChatsArray()));

            return $this->getResponse();
        }

        $chats = $this->getRequestedChats();

        return $this->renderTemplate('ChatBundle:Admin:requestedChats.twig.' . $_format, array(
            'chats' => $chats));
    }

    public function currentVisitsAction($_format)
    {
        if (!is_null($response = $this->checkLogin())) {
            $this->getResponse()->setStatusCode(401);
            $this->getResponse()->setContent('');
            return $this->getResponse();
        }

        if ($_format == 'json') {
            $visits = $this->getDocumentManager()->getRepository('ChatBundle:Visit')->getLastVisitsArray();
            $this->getResponse()->setContent(json_encode($visits));

            return $this->getResponse();
        }

        throw new NotFoundHttpException('Not supported format', $previous);

        return $this->renderTemplate('ChatBundle:Admin:currentVisits.twig.' . $_format, array(
            'visits' => $visits));
    }

    /**
     * @return Application\ChatBundle\Document\Session
     */
    private function getChatSession($id)
    {
        return $this->getDocumentManager()->getRepository('ChatBundle:Session')->find($id);
    }

    public function closeChatAction($id)
    {
        if ($chat = $this->getChatSession($id)) {
            $chat->close();
            $this->getDocumentManager()->persist($chat);
            $this->getDocumentManager()->flush();
        }

        return $this->redirect($this->generateUrl('sglc_admin_console_sessions'));
    }

    public function operatorsAction()
    {
        if (!is_null($response = $this->checkLogin())) {
            return $response;
        }

        $operators = $this->getDocumentManager()->getRepository('ChatBundle:Operator')->findAll();
        $msg = $this->getHttpSession()->getFlash('msg', '');
        return $this->renderTemplate('ChatBundle:Admin:operators.twig.html', array(
            'operators' => $operators,
            'msg' => $msg));
    }

    public function operatorDepartmentAction($id = null)
    {
        if (!is_null($response = $this->checkLogin())) {
            return $response;
        }

        $message = null;

        if ($id) {
            $department = $this->getDocumentManager()->find('ChatBundle:Operator\Department', $id);
        } else {
            $department = new Department();
        }

        $form = new OperatorDepartmentForm('department', $department, $this->get('validator'));

        switch ($this->getRequest()->getMethod()) {
            case 'POST':
            case 'PUT':
                $params = $this->getRequest()->request->get($form->getName());
                if (!empty($params['name'])) {
                    $department->setName($params['name']);
                    $department->setIsActive(isset($params['isActive']) && $params['isActive']);
                    $this->getDocumentManager()->persist($department);
                    $this->getDocumentManager()->flush();
                    $this->getHttpSession()->setFlash('msg', 'The department has been successfully updated');

                    return $this->redirect($this->generateUrl('sglc_admin_operator_departments'));
                }
                //}
                break;
            case 'DELETE':
                break;
        }

        return $this->renderTemplate('ChatBundle:Admin:operator-department.twig.html', array(
            'department' => $department,
            'form' => $form));
    }

    public function operatorDepartmentsAction()
    {
        $this->checkLogin();

        $departments = $this->getDocumentManager()->getRepository('ChatBundle:Operator\Department')->findAll();
        $msg = $this->getHttpSession()->getFlash('msg', '');

        return $this->renderTemplate('ChatBundle:Admin:operator-departments.twig.html', array(
            'departments' => $departments,
            'msg' => $msg));
    }

    /**
     *
     */
    public function operatorAction($id = null)
    {
        if (!is_null($response = $this->checkLogin())) {
            return $response;
        }

        $message = null;

        if ($id) {
            $operator = $this->getDocumentManager()->find('ChatBundle:Operator', $id);
        } else {
            $operator = new Operator();
        }

        $form = new OperatorForm('operator', $operator, $this->get('validator'));

        switch ($this->getRequest()->getMethod()) {
            case 'POST':
            case 'PUT':
                $params = $this->getRequest()->request->get($form->getName());
                if (!empty($params['name']) && !empty($params['email']['first']) && !empty($params['passwd']['first'])) {
                    $operator->setName($params['name']);
                    $operator->setEmail($params['email']['first']);
                    $operator->setPasswd($params['passwd']['first']);
                    $operator->setIsActive(isset($params['isActive']) && $params['isActive']);
                    $this->getDocumentManager()->persist($operator);
                    $this->getDocumentManager()->flush();
                    $this->getHttpSession()->setFlash('msg', 'The operator has been successfully updated');

                    return $this->redirect($this->generateUrl('sglc_admin_operators'));
                }
                //}
                break;
            case 'DELETE':
                break;
        }

        return $this->renderTemplate('ChatBundle:Admin:operator.twig.html', array(
            'operator' => $operator,
            'form' => $form));
    }

}