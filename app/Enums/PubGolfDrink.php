<?php

namespace App\Enums;

enum PubGolfDrink: string
{
    case CarlingBlackLabel = 'carling_black_label';
    case CastleLager = 'castle_lager';
    case CastleLite = 'castle_lite';
    case HansaPilsener = 'hansa_pilsener';
    case WindhoekLager = 'windhoek_lager';
    case WindhoekDraught = 'windhoek_draught';
    case Heineken = 'heineken';
    case Corona = 'corona';
    case AmstelLager = 'amstel_lager';
    case StellaArtois = 'stella_artois';
    case CastleMilkStout = 'castle_milk_stout';
    case FlyingFishLemon = 'flying_fish_lemon';
    case FlyingFishPassion = 'flying_fish_passion';
    case CraftBeer = 'craft_beer';
    case SavannaDry = 'savanna_dry';
    case SavannaLight = 'savanna_light';
    case SavannaAngryLemon = 'savanna_angry_lemon';
    case HuntersDry = 'hunters_dry';
    case HuntersGold = 'hunters_gold';
    case HuntersExtreme = 'hunters_extreme';
    case BrutalFruit = 'brutal_fruit';
    case SmirnoffSpin = 'smirnoff_spin';
    case SmirnoffStorm = 'smirnoff_storm';
    case BerniniBlush = 'bernini_blush';
    case RedSquare = 'red_square';
    case BelgraviaDryLemon = 'belgravia_dry_lemon';
    case KlipdriftAndCola = 'klipdrift_and_cola';
    case KlippiesAndCoke = 'klippies_and_coke';
    case BrandyAndCoke = 'brandy_and_coke';
    case WhiskyAndCoke = 'whisky_and_coke';
    case GinAndTonic = 'gin_and_tonic';
    case VodkaLime = 'vodka_lime';
    case RumAndCoke = 'rum_and_coke';
    case MainstayAndCoke = 'mainstay_and_coke';
    case Amarula = 'amarula';
    case FourthStreet = 'fourth_street';
    case RedWine = 'red_wine';
    case WhiteWine = 'white_wine';
    case Rose = 'rose';
    case JcLeRoux = 'jc_le_roux';
    case Tequila = 'tequila';
    case Springbokkie = 'springbokkie';
    case Jagermeister = 'jagermeister';
    case Jagerbomb = 'jagerbomb';
    case Aftershock = 'aftershock';

    public function label(): string
    {
        return match ($this) {
            self::CarlingBlackLabel => 'Black Label',
            self::CastleLager => 'Castle Lager',
            self::CastleLite => 'Castle Lite',
            self::HansaPilsener => 'Hansa Pilsener',
            self::WindhoekLager => 'Windhoek Lager',
            self::WindhoekDraught => 'Windhoek Draught',
            self::Heineken => 'Heineken',
            self::Corona => 'Corona',
            self::AmstelLager => 'Amstel Lager',
            self::StellaArtois => 'Stella Artois',
            self::CastleMilkStout => 'Castle Milk Stout',
            self::FlyingFishLemon => 'Flying Fish Lemon',
            self::FlyingFishPassion => 'Flying Fish Passion',
            self::CraftBeer => 'Craft beer',
            self::SavannaDry => 'Savanna Dry',
            self::SavannaLight => 'Savanna Light',
            self::SavannaAngryLemon => 'Savanna Angry Lemon',
            self::HuntersDry => "Hunter's Dry",
            self::HuntersGold => "Hunter's Gold",
            self::HuntersExtreme => "Hunter's Extreme",
            self::BrutalFruit => 'Brutal Fruit',
            self::SmirnoffSpin => 'Smirnoff Spin',
            self::SmirnoffStorm => 'Smirnoff Storm',
            self::BerniniBlush => 'Bernini Blush',
            self::RedSquare => 'Red Square',
            self::BelgraviaDryLemon => 'Belgravia Dry Lemon',
            self::KlipdriftAndCola => 'Klipdrift & Cola',
            self::KlippiesAndCoke => 'Klippies & Coke',
            self::BrandyAndCoke => 'Brandy & Coke',
            self::WhiskyAndCoke => 'Whisky & Coke',
            self::GinAndTonic => "Gordon's & Tonic",
            self::VodkaLime => 'Vodka lime',
            self::RumAndCoke => 'Rum & Coke',
            self::MainstayAndCoke => 'Mainstay & Coke',
            self::Amarula => 'Amarula',
            self::FourthStreet => '4th Street',
            self::RedWine => 'Red wine',
            self::WhiteWine => 'White wine',
            self::Rose => 'Rosé',
            self::JcLeRoux => 'JC Le Roux',
            self::Tequila => 'Tequila',
            self::Springbokkie => 'Springbokkie',
            self::Jagermeister => 'Jägermeister',
            self::Jagerbomb => 'Jägerbomb',
            self::Aftershock => 'Aftershock',
        };
    }

    public function category(): PubGolfDrinkCategory
    {
        return match ($this) {
            self::CarlingBlackLabel,
            self::CastleLager,
            self::CastleLite,
            self::HansaPilsener,
            self::WindhoekLager,
            self::WindhoekDraught,
            self::Heineken,
            self::Corona,
            self::AmstelLager,
            self::StellaArtois,
            self::CastleMilkStout,
            self::FlyingFishLemon,
            self::FlyingFishPassion,
            self::CraftBeer => PubGolfDrinkCategory::Beer,
            self::SavannaDry,
            self::SavannaLight,
            self::SavannaAngryLemon,
            self::HuntersDry,
            self::HuntersGold,
            self::HuntersExtreme => PubGolfDrinkCategory::Cider,
            self::BrutalFruit,
            self::SmirnoffSpin,
            self::SmirnoffStorm,
            self::BerniniBlush,
            self::RedSquare,
            self::BelgraviaDryLemon,
            self::KlipdriftAndCola => PubGolfDrinkCategory::Rtd,
            self::KlippiesAndCoke,
            self::BrandyAndCoke,
            self::WhiskyAndCoke,
            self::GinAndTonic,
            self::VodkaLime,
            self::RumAndCoke,
            self::MainstayAndCoke,
            self::Amarula => PubGolfDrinkCategory::Spirit,
            self::FourthStreet,
            self::RedWine,
            self::WhiteWine,
            self::Rose,
            self::JcLeRoux => PubGolfDrinkCategory::Wine,
            self::Tequila,
            self::Springbokkie,
            self::Jagermeister,
            self::Jagerbomb,
            self::Aftershock => PubGolfDrinkCategory::Shooter,
        };
    }

    public function standardDrinks(): float
    {
        return match ($this) {
            self::CastleLite, self::SavannaLight => 0.8,
            self::CastleMilkStout, self::KlippiesAndCoke, self::BrandyAndCoke => 1.2,
            self::HuntersExtreme => 1.6,
            self::FourthStreet, self::RedWine, self::WhiteWine, self::Rose, self::JcLeRoux => 1.3,
            self::Tequila, self::Springbokkie, self::Jagermeister, self::Aftershock => 0.6,
            default => 1.0,
        };
    }

    public function imageUrl(): ?string
    {
        foreach (['jpg', 'png', 'webp'] as $extension) {
            $path = 'images/pub-golf/'.$this->value.'.'.$extension;

            if (is_file(public_path($path))) {
                return asset($path);
            }
        }

        return null;
    }
}
