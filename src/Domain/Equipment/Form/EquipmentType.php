<?php

namespace App\Domain\Equipment\Form;

use App\Domain\Equipment\Entity\Equipment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class EquipmentType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder->add("name", TextType::class, [
            "label" => "equipment.modal.name_label",
            "constraints" => [
                new NotBlank(message: "equipment.validation.name_required"),
                new Length(max: 510),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => Equipment::class,
            // Stateful CSRF id (not in framework stateless_token_ids) so the
            // token validates reliably when the modal is submitted via fetch.
            "csrf_token_id" => "equipment_form",
        ]);
    }
}
