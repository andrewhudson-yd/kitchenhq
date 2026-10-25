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
        ['Cupboard', 'spray oil|cooking spray|frylight|ground |blend|sumac|allspice|clove|sesame|seeds|five spice|cardamom|star anise|nutmeg|fennel'],
        ['Toiletries', 'hand wash|body wash|face wash|hand soap|baby wipes|make-up|hand cream|sun cream|after sun|hand sanitiser|nail varnish'],
        ['Cleaning & household', 'toilet roll|toilet paper|toilet clean|toilet block|kitchen roll|foil|cling film|baking paper|bin bag|food bag|freezer bag|washing|laundry|fabric|stain|dishwasher|rinse aid|bleach|clean|disinfect|limescale|spray|wipes|sponge|scourer|cloth|rubber glove|detergent|air freshener|candle|light bulb|batteries|battery|matches|dog food|cat food|cat litter|pet food'],
        ['Toiletries', 'tooth|mouthwash|floss|shampoo|conditioner|shower gel|bubble bath|soap|deodorant|moisturis|lotion|lip balm|cotton|razor|shaving|hair|sanitary|tampon|panty liner|tissue|nappy|nappies|plaster|paracetamol|ibuprofen|antihistamine|tablets|vitamin|medicine|toiletr'],
        ['Snacks & drinks', 'cereal bar|hot chocolate|chocolate bar|milk chocolate|juice'],
        ['Dairy & eggs', 'spreadable butter|butter / spread|margarine'],
        ['Cupboard', 'simplycook|chocolate chip|stock|gravy|granules|tinned|tin of|chopped tomato|passata|tomato pur|ketchup|mayo|sauce|salad cream|mustard|chutney|pickle|jam|marmalade|spread|peanut butter|honey|syrup|coconut milk|baked bean|kidney bean|butter bean|chickpea|lentil|black pepper|powder|flakes|dried|mixed herbs|paste|vinegar|oil|custard|rice pudding|flour|sugar|noodle|jelly|seasoning|spice|paprika|cumin|turmeric|oregano|cinnamon|garam masala|curry'],
        ['Frozen', 'frozen|roast potato|spring roll|onion ring|mozzarella stick|ice cream|ice lol|lollies|ice cube|fish finger|chips|fries|waffle|hash brown|nugget|dipper|goujon|kiev|quorn|yorkshire'],
        ['Chilled', 'fresh pasta|fresh soup|garlic bread|hummus|coleslaw|pesto|ready meal'],
        ['Meat & fish', 'chicken|beef|pork|lamb|turkey|mince|sausage|bacon|ham|chorizo|salami|pepperoni|steak|burger|quarter pounder|meatball|gammon|fish|cod|haddock|salmon|tuna steak|prawn|mackerel'],
        ['Dairy & eggs', 'milk|butter|cheese|cheddar|mozzarella|halloumi|feta|parmesan|yoghurt|yogurt|cream|crème|creme|fromage|egg'],
        ['Fruit & veg', 'apple|banana|berr|strawberr|raspberr|blueberr|blackberr|cranberr|grape|orange|satsuma|clementine|lemon|lime|melon|pineapple|peach|pear|plum|mango|kiwi|avocado|tomato|potato|onion|garlic|carrot|broccoli|cauliflower|cabbage|sprout|lettuce|salad|spinach|rocket|cucumber|pepper|mushroom|leek|courgette|aubergine|celery|green bean|sweetcorn|herb|coriander|parsley|basil|mint|ginger|chilli|asparagus|swede|parsnip|beetroot|butternut|pea|fruit|veg'],
        ['Bakery', 'bread|roll|wrap|pitta|naan|bagel|crumpet|baguette|bun|croissant|muffin|teacake|flatbread'],
        ['Snacks & drinks', 'crisp|biscuit|chocolate|sweet|popcorn|nut|raisin|juice|squash|fizzy|cola|lemonade|water|tea|coffee|wine|beer|cake'],
        ['Cupboard', 'pasta|spaghetti|penne|macaroni|lasagne|rice|oat|porridge|couscous|quinoa|cereal|cornflake|weetabix|granola|salt|bean|soup|cracker'],
    ];

    /** Kitchen shelves: the shop sections, with the big Cupboard section split into smaller groups. */
    public const SHELVES = ['Fruit & veg', 'Bakery', 'Meat & fish', 'Dairy & eggs', 'Chilled', 'Frozen', 'Pasta, rice & grains', 'Tins & jars', 'Sauces & condiments', 'Herbs, spices & stock', 'Breakfast & spreads', 'Baking', 'Cupboard', 'Snacks & drinks', 'Toiletries', 'Cleaning & household', 'Other'];

    /** Ordered keyword rules for splitting cupboard things up. */
    private const CUPBOARD_RULES = [
        ['Breakfast & spreads', 'chocolate spread|peanut butter|jam|marmalade|honey|syrup|spread|cereal|cornflake|weetabix|granola|bran|muesli|porridge|oat'],
        ['Baking', 'flour|sugar|baking|bicarbonate|cocoa|vanilla|chocolate chip|yeast|icing|cornflour'],
        ['Tins & jars', 'tin|chopped tomato|passata|tomato pur|bean|chickpea|lentil|coconut milk|soup|sweetcorn|tuna|custard|rice pudding|simplycook'],
        ['Herbs, spices & stock', 'stock|gravy|granules|ground |blend|sumac|allspice|clove|sesame|seeds|five spice|cardamom|star anise|fennel|spice|paprika|cumin|turmeric|oregano|cinnamon|garam masala|curry powder|chilli powder|chilli flakes|herbs|salt|pepper|seasoning|aromat|bay lea|thyme|rosemary|nutmeg'],
        ['Sauces & condiments', 'sauce|ketchup|mayo|mustard|chutney|pickle|vinegar|oil|cooking spray|frylight|salad cream|paste|pesto|dressing|relish|jelly|curry'],
        ['Pasta, rice & grains', 'pasta|spaghetti|penne|macaroni|lasagne|farfalle|fusilli|tagliatelle|linguine|orzo|gnocchi|noodle|rice|couscous|quinoa|bulgur'],
        ['Snacks & drinks', 'cracker|dried fruit|raisin|sultana'],
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
        if (preg_match('~\\bsoup~u', $food) && ! str_contains($food, 'fresh soup')) {
            return match ($place ?? self::placeFor($name, '')) {
                'fridge' => 'Chilled',
                'freezer' => 'Frozen',
                default => 'Cupboard',
            };
        }
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

    /** The section an item shows under: the one picked for it, or the automatic one. */
    public static function shelfFor(object $item): string
    {
        return in_array($item->shelf ?? null, self::SHELVES, true) ? $item->shelf : self::shelf($item->name, $item->location?->type);
    }

    /** The supermarket section for a kitchen shelf: cupboard groups such as "Tins & jars" are all in "Cupboard". */
    public static function sectionForShelf(string $shelf): string
    {
        return in_array($shelf, self::SECTIONS, true) ? $shelf : 'Cupboard';
    }

    /** Which kitchen shelf group an item belongs to, e.g. "Tins & jars" rather than just "Cupboard". */
    public static function shelf(string $name, ?string $place = null): string
    {
        $section = self::section($name, $place);
        if ($section !== 'Cupboard') {
            return $section;
        }
        $food = Food::name($name);
        foreach (self::CUPBOARD_RULES as [$shelf, $words]) {
            if (preg_match('~\\b(?:'.$words.')~u', $food)) {
                return $shelf;
            }
        }

        return 'Cupboard';
    }
}
