<?php

namespace App\Support;

class FoodDictionary
{
    public const PLACES = ['fridge' => 'Fridge', 'freezer' => 'Freezer', 'pantry' => 'Cupboard'];

    /** Shop sections in the order you usually walk round a supermarket. */
    public const SECTIONS = ['Fruit & veg', 'Bakery', 'Meat & fish', 'Dairy & eggs', 'Chilled', 'Frozen', 'Cupboard', 'Snacks & drinks', 'Toiletries', 'Cleaning & household', 'Other'];

    /** Non-food lists in the dictionary: shown with their own label and kept in the cupboard. */
    public const EXTRAS = ['toiletries' => 'Toiletries', 'cleaning' => 'Cleaning & household'];

    /** Ordered keyword rules: earlier rules win, so specific foods come before broad words like "chicken" or "milk". */
    private const SECTION_RULES = [
        ['Cupboard', 'spray oil|cooking spray|frylight'],
        ['Toiletries', 'hand wash|body wash|face wash|hand soap|baby wipes|make-up|hand cream|sun cream|after sun|hand sanitiser|nail varnish'],
        ['Cleaning & household', 'toilet roll|toilet paper|toilet clean|toilet block|kitchen roll|foil|cling film|baking paper|bin bag|food bag|freezer bag|washing|laundry|fabric|stain|dishwasher|rinse aid|bleach|clean|disinfect|limescale|spray|wipes|sponge|scourer|cloth|rubber glove|detergent|air freshener|candle|light bulb|batteries|battery|matches|dog food|cat food|cat litter|pet food'],
        ['Toiletries', 'tooth|mouthwash|floss|shampoo|conditioner|shower gel|bubble bath|soap|deodorant|moisturis|lotion|lip balm|cotton|razor|shaving|hair|sanitary|tampon|panty liner|tissue|nappy|nappies|plaster|paracetamol|ibuprofen|antihistamine|tablets|vitamin|medicine|toiletr'],
        ['Snacks & drinks', 'cereal bar|hot chocolate|chocolate bar|milk chocolate|juice'],
        ['Dairy & eggs', 'spreadable butter|butter / spread|margarine'],
        ['Cupboard', 'simplycook|chocolate chip|stock|gravy|granules|tinned|tin of|chopped tomato|passata|tomato pur|ketchup|mayo|sauce|salad cream|mustard|chutney|pickle|jam|marmalade|spread|peanut butter|honey|syrup|coconut milk|baked bean|kidney bean|butter bean|chickpea|lentil|black pepper|powder|flakes|dried|mixed herbs|paste|vinegar|oil|custard|rice pudding|flour|sugar|noodle|jelly|seasoning|spice|paprika|cumin|turmeric|oregano|cinnamon|garam masala|curry'],
        ['Frozen', 'frozen|roast potato|ice cream|ice lol|lollies|ice cube|fish finger|chips|fries|waffle|hash brown|nugget|dipper|goujon|kiev|quorn|yorkshire'],
        ['Chilled', 'fresh pasta|fresh soup|garlic bread|hummus|coleslaw|pesto|ready meal'],
        ['Meat & fish', 'chicken|beef|pork|lamb|turkey|mince|sausage|bacon|ham|chorizo|salami|pepperoni|steak|burger|quarter pounder|meatball|gammon|fish|cod|haddock|salmon|tuna steak|prawn|mackerel'],
        ['Dairy & eggs', 'milk|butter|cheese|cheddar|mozzarella|halloumi|feta|parmesan|yoghurt|yogurt|cream|crème|creme|fromage|egg'],
        ['Fruit & veg', 'apple|banana|berr|strawberr|raspberr|blueberr|blackberr|cranberr|grape|orange|satsuma|clementine|lemon|lime|melon|pineapple|peach|pear|plum|mango|kiwi|avocado|tomato|potato|onion|garlic|carrot|broccoli|cauliflower|cabbage|sprout|lettuce|salad|spinach|rocket|cucumber|pepper|mushroom|leek|courgette|aubergine|celery|green bean|sweetcorn|herb|coriander|parsley|basil|mint|ginger|chilli|asparagus|swede|parsnip|beetroot|butternut|pea|fruit|veg'],
        ['Bakery', 'bread|roll|wrap|pitta|naan|bagel|crumpet|baguette|bun|croissant|muffin|teacake|flatbread'],
        ['Snacks & drinks', 'crisp|biscuit|chocolate|sweet|popcorn|nut|raisin|juice|squash|fizzy|cola|lemonade|water|tea|coffee|wine|beer|cake'],
        ['Cupboard', 'pasta|spaghetti|penne|macaroni|lasagne|rice|oat|porridge|couscous|quinoa|cereal|cornflake|weetabix|granola|salt|bean|soup|cracker'],
    ];

    private static ?array $foods = null;

    private static array $labels = [];

    /** @return array<string, string> food name => usual place (fridge, freezer, pantry) */
    public static function all(): array
    {
        if (self::$foods === null) {
            $data = json_decode(file_get_contents(database_path('data/kitchen-hq/food_dictionary.json')), true, 512, JSON_THROW_ON_ERROR);
            self::$foods = [];
            foreach (array_keys(self::PLACES) as $place) {
                foreach ($data[$place] ?? [] as $name) {
                    self::$foods[$name] ??= $place;
                }
            }
            foreach (self::EXTRAS as $list => $label) {
                foreach ($data[$list] ?? [] as $name) {
                    self::$foods[$name] ??= 'pantry';
                    self::$labels[Food::name($name)] ??= $label;
                }
            }
        }

        return self::$foods;
    }

    public static function placeFor(string $name, string $fallback = 'pantry'): string
    {
        $wanted = Food::name($name);
        foreach (self::all() as $food => $place) {
            if (Food::name($food) === $wanted) {
                return $place;
            }
        }

        return $fallback;
    }

    /** Which shop section a food is usually found in. Falls back to where it is kept at home. */
    public static function section(string $name, ?string $place = null): string
    {
        $food = Food::name($name);
        foreach (self::SECTION_RULES as [$section, $words]) {
            if (preg_match('~\\b(?:'.$words.')~u', $food)) {
                return $section;
            }
        }

        return match ($place ?? self::placeFor($name, '')) {
            'freezer' => 'Frozen',
            'fridge' => 'Chilled',
            'pantry' => 'Cupboard',
            default => 'Other',
        };
    }

    /** A label to show instead of the storage place, e.g. "Toiletries" for toothpaste. */
    public static function label(string $name): ?string
    {
        self::all();

        return self::$labels[Food::name($name)] ?? null;
    }
}
