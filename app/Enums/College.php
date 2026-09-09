<?php

namespace App\Enums;

enum College: string
{
    case InformationTechnology = 'information_technology';
    case RailwayTechnology = 'railway_technology';
    case TextileTechnology = 'textile_technology';
    case FoodIndustryTechnology = 'food_industry_technology';
    case AgriculturalEquipment = 'agricultural_equipment';
    case DentalLaboratory = 'dental_laboratory';
    case PharmaceuticalProduction = 'pharmaceutical_production';
    case HealthInformationManagement = 'health_information_management';
    case HealthCareTechnology = 'health_care_technology';
    case HealthScienceBasic = 'health_science_basic';

    public function label(): string
    {
        return match ($this) {
            self::InformationTechnology => 'تكنولوجيا المعلومات',
            self::RailwayTechnology => 'تكنولوجيا السكك الحديدية',
            self::TextileTechnology => 'تكنولوجيا تشغيل وصيانة الغزل والنسيج',
            self::FoodIndustryTechnology => 'تكنولوجيا الصناعات الغذائية',
            self::AgriculturalEquipment => 'تكنولوجيا الجرارات والمعدات الزراعية',
            self::DentalLaboratory => 'تكنولوجيا معامل الأسنان',
            self::PharmaceuticalProduction => 'تكنولوجيا الإنتاج الدوائي',
            self::HealthInformationManagement => 'تكنولوجيا إدارة المعلومات الصحية',
            self::HealthCareTechnology => 'تكنولوجيا الرعاية الصحية',
            self::HealthScienceBasic => 'العلوم الصحية الأساسية',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function payload(): array
    {
        return array_map(
            fn (self $college): array => [
                'value' => $college->value,
                'label' => $college->label(),
            ],
            self::cases(),
        );
    }
}
