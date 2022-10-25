<?php

namespace App\ParamConverter;

use App\Entity\Event;
use Doctrine\Persistence\ManagerRegistry as Registry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventYearParamConverter implements ParamConverterInterface
{
    /**
     * @param Registry $registry Manager registry
     */
    public function __construct(private readonly Registry $registry)
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function getEntityClassName(): string
    {
        return Event::class;
    }
    /**
     * {@inheritdoc}
     *
     * Check, if object supported by our converter
     */
    public function supports(ParamConverter $configuration): bool
    {
        // If there is no manager, this means that only Doctrine DBAL is configured
        // In this case we can do nothing and just return
        if (null === $this->registry || !count($this->registry->getManagers())) {
            return false;
        }
        $class = $configuration->getClass();
        // Check, if option class was set in configuration
        if (null === $class || 'App:Event' !== $class) {
            return false;
        }
        // Get actual entity manager for class
        $em = $this->registry->getManagerForClass($this->getEntityClassName());
        $name = $em->getClassMetadata($this->getEntityClassName())->getName();
        // Check, if class name is what we need
        if (\App\Entity\Event::class !== $name) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Applies converting
     *
     * @throws \InvalidArgumentException When route attributes are missing
     * @throws NotFoundHttpException     When object not found
     */
    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $slug = $request->attributes->get('slug');
        $year  = $request->attributes->get('year');

        // Check, if route attributes exists
        if (null === $slug || null === $year) {
            throw new \InvalidArgumentException('Route attribute is missing');
        }

        // Get actual entity manager for class
        $em = $this->registry->getManagerForClass($this->getEntityClassName());

        $eventRepository = $em->getRepository($this->getEntityClassName());

        // Try to find Event by its slug and year
        $event = $eventRepository->findEventBySlugAndYear($slug, $year);

        if (null === $event || !($event instanceof Event)) {
            throw new NotFoundHttpException(sprintf('%s object not found.', $configuration->getClass()));
        }

        // Map found Event to the route's parameter
        $request->attributes->set($configuration->getName(), $event);
        return true;
    }
}
