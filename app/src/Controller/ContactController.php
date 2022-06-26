<?php
/**
 * Contact controller.
 */

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Form\Type\ContactType;
use App\Repository\ContactRepository;
use App\Service\ContactServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ContactController.
 */
#[Route('/contact')]
class ContactController extends AbstractController
{
    /**
     * Contact service.
     */
    private ContactServiceInterface $contactService;

    /**
     * Translator.
     */
    private TranslatorInterface $translator;

    public function __construct(ContactServiceInterface $contactService, TranslatorInterface $translator)
    {
        $this->contactService = $contactService;
        $this->translator = $translator;
    }

    /**
     * Index action.
     *
     * @param Request $request HTTP Request
     *
     * @return Response HTTP response
     */
    #[Route(
        name: 'contact_index',
        methods: 'GET'
    )]
    public function index(Request $request): Response
    {
        $filters = $this->getFilters($request);

        /** @var User $user */
        $user = $this->getUser();
        $pagination = $this->contactService->getPaginatedList(
            $request->query->getInt('page', 1),
            $user,
            $filters
        );

        return $this->render('contact/index.html.twig', ['pagination' => $pagination]);
    }

    /**
     * Show action.
     *
     * @param ContactRepository $repository Record repository
     * @param int               $id         Record id
     *
     * @return Response HTTP response
     */
    #[Route(
        '/{id}',
        name: 'contact_show',
        requirements: ['id' => '[1-9]\d*'],
        methods: 'GET'
    )]
    public function show(ContactRepository $repository, int $id): Response
    {
        $contact = $repository->findOneById($id);

        if ($contact->getAuthor() !== $this->getUser()) {
            $this->addFlash(
                'warning',
                $this->translator->trans('message.record_not_found')
            );

            return $this->redirectToRoute('contact_index');
        }

        return $this->render(
            'contact/show.html.twig',
            ['contact' => $contact]
        );
    }

    /**
     * Create action.
     *
     * @param Request $request HTTP request
     *
     * @return Response HTTP response
     */
    #[Route(
        '/create',
        name: 'contact_create',
        methods: 'GET|POST',
    )]
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $contact = new Contact();
        $contact->setAuthor($user);
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->contactService->save($contact);

            $this->addFlash(
                'success',
                $this->translator->trans('message.created_successfully')
            );

            return $this->redirectToRoute('contact_index');
        }

        return $this->render(
            'contact/create.html.twig',
            ['form' => $form->createView()]
        );
    }

    /**
     * Edit action.
     *
     * @param Request $request HTTP request
     * @param Contact $contact Contact entity
     *
     * @return Response HTTP response
     */
    #[Route('/{id}/edit', name: 'contact_edit', requirements: ['id' => '[1-9]\d*'], methods: 'GET|PUT')]
    public function edit(Request $request, Contact $contact): Response
    {
        $form = $this->createForm(ContactType::class, $contact, [
            'method' => 'PUT',
            'action' => $this->generateUrl('contact_edit', ['id' => $contact->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->contactService->save($contact);

            $this->addFlash(
                'success',
                $this->translator->trans('message.edited_successfully')
            );

            return $this->redirectToRoute('contact_index');
        }

        if ($contact->getAuthor() !== $this->getUser()) {
            $this->addFlash(
                'warning',
                $this->translator->trans('message.record_not_found')
            );

            return $this->redirectToRoute('contact_index');
        }

        return $this->render(
            'contact/edit.html.twig',
            [
                'form' => $form->createView(),
                'contact' => $contact,
            ]
        );
    }

    /**
     * Delete action.
     *
     * @param Request $request HTTP request
     * @param Contact $contact Contact entity
     *
     * @return Response HTTP response
     */
    #[Route('/{id}/delete', name: 'contact_delete', requirements: ['id' => '[1-9]\d*'], methods: 'GET|DELETE')]
    public function delete(Request $request, Contact $contact): Response
    {
        $form = $this->createForm(FormType::class, $contact, [
            'method' => 'DELETE',
            'action' => $this->generateUrl('contact_delete', ['id' => $contact->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->contactService->delete($contact);

            $this->addFlash(
                'success',
                $this->translator->trans('message.deleted_successfully')
            );

            return $this->redirectToRoute('contact_index');
        }

        if ($contact->getAuthor() !== $this->getUser()) {
            $this->addFlash(
                'warning',
                $this->translator->trans('message.record_not_found')
            );

            return $this->redirectToRoute('contact_index');
        }

        return $this->render(
            'contact/delete.html.twig',
            [
                'form' => $form->createView(),
                'contact' => $contact,
            ]
        );
    }

    /**
     * Search action.
     *
     * @param Request $request HTTP request
     *
     * @return Response HTTP response
     */
    #[Route('/search', name: 'contact_search', requirements: ['id' => '[1-9]\d*'], methods: 'GET')]
    public function search(Request $request): Response
    {
        $pattern = $this->getPattern($request);

        /** @var User $user */
        $user = $this->getUser();
        $pagination = $this->contactService->getPaginatedListSearch(
            $request->query->getInt('page', 1),
            $user,
            $pattern
        );

        return $this->render('contact/index.html.twig', ['pagination' => $pagination]);
    }

    /**
     * Get filters from request.
     *
     * @param Request $request HTTP request
     *
     * @return array<string, int> Array of filters
     *
     * @psalm-return array{category_id: int, tag_id: int, status_id: int}
     */
    private function getFilters(Request $request): array
    {
        $filters = [];
        $filters['category_id'] = $request->query->getInt('filters_category_id');
        $filters['tag_id'] = $request->query->getInt('filters_tag_id');

        return $filters;
    }

    /**
     * Get pattern from request.
     *
     * @param Request $request HTTP request
     *
     * @return string Pattern
     */
    private function getPattern(Request $request): string
    {
        return $request->query->getAlnum('pattern', '');
    }
}
