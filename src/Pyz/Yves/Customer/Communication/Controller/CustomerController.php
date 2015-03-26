<?php
namespace Pyz\Yves\Customer\Communication\Controller;

use SprykerCore\Yves\Application\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use ProjectA\Shared\Customer\Transfer\Customer as CustomerTransfer;

class CustomerController extends AbstractController
{
    public function loginAction(Request $request)
    {
        if ($this->isGranted("ROLE_USER")) {
            $this->addMessageWarning("customer.already.authenticated");
            return $this->redirectResponseInternal("home");
        }

        return [
            "error" => $this->getSecurityError($request),
        ];
    }

    public function logoutAction()
    {
        $this->locator->customer()
            ->pluginSecurityService()
            ->createUserProvider()
            ->logout($this->getUser()->getUsername());
        return $this->redirectResponseInternal("home");
    }

    public function registerAction()
    {
        $form = $this->createForm(
            $registration = $this->locator->customer()
                ->pluginSecurityService()
                ->createFormRegister()
        );

        if ($form->isValid()) {
            /** @var CustomerTransfer $customerTransfer */
            $customerTransfer = $this->locator->customer()->transferCustomer();
            $customerTransfer->setEmail($form->getData()["email"]);
            $customerTransfer->setPassword($form->getData()["password"]);
            $customerTransfer = $this->locator->customer()->sdk()->registerCustomer($customerTransfer);
            if ($customerTransfer->getRegistrationKey()) {
                $this->addMessageWarning("customer.registration.success");
                return $this->redirectResponseInternal("login");
            }
        }

        return ["registerForm" => $form->createView()];
    }

    public function confirmRegistrationAction(Request $request)
    {
        /** @var CustomerTransfer $customerTransfer */
        $customerTransfer = $this->locator->customer()->transferCustomer();
        $customerTransfer->setRegistrationKey($request->query->get("token"));
        $customerTransfer = $this->locator->customer()->sdk()->confirmRegistration($customerTransfer);
        if ($customerTransfer->getRegistered()) {
            $this->addMessageSuccess("customer.registration.confirmed");
            return $this->redirectResponseInternal("login");
        }
        $this->addMessageError("customer.registration.timeout");
        return $this->redirectResponseInternal("home");
    }

    public function deleteAction()
    {
        $form = $this->createForm(
            $registration = $this->locator->customer()
                ->pluginSecurityService()
                ->createFormDelete()
        );

        if ($form->isValid()) {
            /** @var CustomerTransfer $customerTransfer */
            $customerTransfer = $this->locator->customer()->transferCustomer();
            $customerTransfer->setEmail($this->getUser()->getUsername());
            if ($this->locator->customer()->sdk()->deleteCustomer($customerTransfer)) {
                $this->locator->customer()
                    ->pluginSecurityService()
                    ->createUserProvider()
                    ->logout($this->getUser()->getUsername());
                return $this->redirectResponseInternal("home");
            } else {
                $this->addMessageError("customer.delete.failed");
            }
        }

        return ["form" => $form->createView()];
    }

    public function profileAction()
    {
        /** @var CustomerTransfer $customerTransfer */
        $customerTransfer = $this->locator->customer()->transferCustomer();

        $form = $this->createForm(
            $this->locator->customer()
                ->pluginCustomerProfile()
                ->createFormProfile()
        );

        if ($form->isValid()) {
            $customerTransfer->fromArray($form->getData());
            $customerTransfer->setEmail($this->getUser()->getUsername());
            $this->locator->customer()->sdk()->updateCustomer($customerTransfer);
            return $this->redirectResponseInternal("profile");
        }

        $customerTransfer->setEmail($this->getUser()->getUsername());
        $customerTransfer = $this->locator->customer()->sdk()->getCustomer($customerTransfer);
        $form->setData([
            "firstName" => $customerTransfer->getFirstName(),
            "middleName" => $customerTransfer->getMiddleName(),
            "lastName" => $customerTransfer->getLastName(),
            "company" => $customerTransfer->getCompany(),
            "dateOfBirth" => $customerTransfer->getDateOfBirth(),
        ]);

        return ["form" => $form->createView()];
    }
}