<?php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
    //        new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),

	// CKEDITOR
	new Ivory\CKEditorBundle\IvoryCKEditorBundle(),
	// Sonata Easy Extends
	new Sonata\EasyExtendsBundle\SonataEasyExtendsBundle(),
	// Sonata ORM
	new Sonata\DoctrineORMAdminBundle\SonataDoctrineORMAdminBundle(),
	// Sonata admin
	new Sonata\CoreBundle\SonataCoreBundle(),
	new Sonata\BlockBundle\SonataBlockBundle(),
	new Knp\Bundle\MenuBundle\KnpMenuBundle(),
	new Sonata\AdminBundle\SonataAdminBundle(),
	// Sonata authentication
	new FOS\UserBundle\FOSUserBundle(),
	new Sonata\UserBundle\SonataUserBundle('FOSUserBundle'),
	// Sonata I18N
    new Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle(),
//	new Sonata\TranslationBundle\SonataTranslationBundle(),
	new Sonata\IntlBundle\SonataIntlBundle(),
	// Sonata Media
	new Sonata\MediaBundle\SonataMediaBundle(),
	new JMS\SerializerBundle\JMSSerializerBundle(),
	new Sonata\ClassificationBundle\SonataClassificationBundle(),
	new Sonata\NotificationBundle\SonataNotificationBundle(),
	// Sonata Cache
	new Sonata\CacheBundle\SonataCacheBundle(),
	// Sonata SEO
	new Sonata\SeoBundle\SonataSeoBundle(),
	// Sonata Page
	new Sonata\PageBundle\SonataPageBundle(),
	new Symfony\Cmf\Bundle\RoutingBundle\CmfRoutingBundle(),
	// ApplicationBundles
	new Application\Sonata\MediaBundle\ApplicationSonataMediaBundle(),
	new Application\Sonata\ClassificationBundle\ApplicationSonataClassificationBundle(),
	new Application\Sonata\UserBundle\ApplicationSonataUserBundle(),
	new Application\Sonata\PageBundle\ApplicationSonataPageBundle(),
	new Application\Sonata\NotificationBundle\ApplicationSonataNotificationBundle(),
	// APIIN TARVITTAVAT
//	new FOS\RestBundle\FOSRestBundle(),
//	new Nelmio\ApiDocBundle\NelmioApiDocBundle(),
        // Timeline
        new Sonata\TimelineBundle\SonataTimelineBundle(),
        new Spy\TimelineBundle\SpyTimelineBundle(),
        new Application\Sonata\TimelineBundle\ApplicationSonataTimelineBundle(),
        // Oauth
		new KnpU\OAuth2ClientBundle\KnpUOAuth2ClientBundle(),
		// Audit
		new SimpleThings\EntityAudit\SimpleThingsEntityAuditBundle(),
        // Tunkki
            new Entropy\TunkkiBundle\EntropyTunkkiBundle(),
        );

        if (in_array($this->getEnvironment(), array('dev', 'test'))) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }
    public function getRootDir()
    {
        return __DIR__;
    }
    public function getCacheDir()
    {
        return dirname(__DIR__).'/var/cache/'.$this->getEnvironment();
    }
    public function getLogDir()
    {
        return dirname(__DIR__).'/var/logs';
    }
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->setParameter('container.autowiring.strict_mode', true);
            $container->setParameter('container.dumper.inline_class_loader', true);
            $container->addObjectResource($this);
        });
        $loader->load($this->getRootDir().'/config/config_'.$this->getEnvironment().'.yml');
    }
}
