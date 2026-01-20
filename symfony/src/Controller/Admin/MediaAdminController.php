<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Sonata\AdminBundle\Bridge\Exporter\AdminExporter;
use Sonata\AdminBundle\Controller\CRUDController;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface;
use Sonata\ClassificationBundle\Model\ContextManagerInterface;
use Sonata\MediaBundle\Model\MediaInterface;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom MediaAdminController that fixes category handling when categories are disabled.
 *
 * This replaces the Sonata MediaAdminController because:
 * 1. The original uses $this->container->has() which returns true for optional services in service locators
 * 2. It doesn't handle empty string category parameter properly
 *
 * @extends CRUDController<MediaInterface>
 */
final class MediaAdminController extends CRUDController
{
    #[\Override]
    public static function getSubscribedServices(): array
    {
        return [
            'sonata.media.pool' => Pool::class,
            'sonata.media.manager.category' => '?'.CategoryManagerInterface::class,
            'sonata.media.manager.context' => '?'.ContextManagerInterface::class,
        ] + parent::getSubscribedServices();
    }

    #[\Override]
    public function createAction(Request $request): Response
    {
        $this->admin->checkAccess('create');

        if ($request->isMethod('get') && null === $request->query->get('provider')) {
            $pool = $this->container->get('sonata.media.pool');
            \assert($pool instanceof Pool);
            $context = $request->query->get('context', $pool->getDefaultContext());
            \assert(\is_string($context));

            return $this->render('@SonataMedia/MediaAdmin/select_provider.html.twig', [
                'providers' => $pool->getProvidersByContext($context),
                'action' => 'create',
            ]);
        }

        return parent::createAction($request);
    }

    #[\Override]
    public function listAction(Request $request): Response
    {
        $this->assertObjectExists($request);

        $this->admin->checkAccess('list');

        $preResponse = $this->preList($request);
        if ($preResponse instanceof Response) {
            return $preResponse;
        }

        $listMode = $request->query->get('_list_mode', 'mosaic');
        \assert(\is_string($listMode));

        $this->admin->setListMode($listMode);

        $datagrid = $this->admin->getDatagrid();

        $filters = $request->query->all('filter');

        // set the default context
        if (\array_key_exists('context', $filters)) {
            $context = $filters['context']['value'];
        } else {
            $pool = $this->container->get('sonata.media.pool');
            \assert($pool instanceof Pool);

            $context = $this->admin->getPersistentParameter('context', $pool->getDefaultContext());
        }

        $datagrid->setValue('context', null, $context);

        $rootCategory = null;

        // Fix: Actually try to get the service instead of just checking has()
        // The has() check returns true for optional services in service locators
        // even when the service doesn't exist in the container
        $categoryManager = $this->getCategoryManagerIfAvailable();
        $contextManager = $this->getContextManagerIfAvailable();

        if ($categoryManager instanceof CategoryManagerInterface && $contextManager instanceof ContextManagerInterface) {
            $rootCategories = $categoryManager->getRootCategoriesForContext($contextManager->find($context));

            if ([] !== $rootCategories) {
                $rootCategory = current($rootCategories);
            }

            if (null !== $rootCategory && [] === $filters) {
                $datagrid->setValue('category', null, $rootCategory->getId());
            }

            // Fix: Check for non-empty string, not just non-null
            $categoryParam = $request->query->get('category');
            if (null !== $categoryParam && '' !== $categoryParam) {
                $categoryId = filter_var($categoryParam, \FILTER_VALIDATE_INT);
                if (false !== $categoryId) {
                    $category = $categoryManager->findOneBy([
                        'id' => $categoryId,
                        'context' => $context,
                    ]);

                    if (null !== $category) {
                        $datagrid->setValue('category', null, $category->getId());
                    } elseif (null !== $rootCategory) {
                        $datagrid->setValue('category', null, $rootCategory->getId());
                    }
                }
            }
        }

        $formView = $datagrid->getForm()->createView();

        $this->setFormTheme($formView, $this->admin->getFilterTheme());

        if ($this->container->has('sonata.admin.admin_exporter')) {
            $exporter = $this->container->get('sonata.admin.admin_exporter');
            \assert($exporter instanceof AdminExporter);
            $exportFormats = $exporter->getAvailableFormats($this->admin);
        }

        return $this->render($this->admin->getTemplateRegistry()->getTemplate('list'), [
            'action' => 'list',
            'form' => $formView,
            'datagrid' => $datagrid,
            'root_category' => $rootCategory,
            'csrf_token' => $this->getCsrfToken('sonata.batch'),
            'export_formats' => $exportFormats ?? $this->admin->getExportFormats(),
        ]);
    }

    private function getCategoryManagerIfAvailable(): ?CategoryManagerInterface
    {
        if (!$this->container->has('sonata.media.manager.category')) {
            return null;
        }

        try {
            $service = $this->container->get('sonata.media.manager.category');

            return $service instanceof CategoryManagerInterface ? $service : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getContextManagerIfAvailable(): ?ContextManagerInterface
    {
        if (!$this->container->has('sonata.media.manager.context')) {
            return null;
        }

        try {
            $service = $this->container->get('sonata.media.manager.context');

            return $service instanceof ContextManagerInterface ? $service : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
