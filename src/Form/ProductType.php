<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Match the existing editor's field names; its HTML and stock fields stay as they are.
        $builder
            ->add('product_id', TextType::class, ['property_path' => 'id', 'required' => false, 'empty_data' => ''])
            ->add('name', TextType::class, ['required' => false, 'empty_data' => ''])
            ->add('type', TextType::class, ['required' => false, 'empty_data' => ''])
            ->add('base_price', TextType::class, ['property_path' => 'basePrice', 'required' => false, 'empty_data' => ''])
            ->add('description', TextareaType::class, ['required' => false, 'empty_data' => ''])
            ->add('image', TextType::class, ['required' => false, 'empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'allow_extra_fields' => true,
            // This editor already validates its existing admin CSRF token.
            'csrf_protection' => false,
        ]);
    }
}
