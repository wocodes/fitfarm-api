<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantCmsContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'section_key',
        'content',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get default baseline content for a specific section.
     */
    public static function getDefaultSectionContent(string $sectionKey, ?Tenant $tenant = null): array
    {
        $tenantName = $tenant ? $tenant->name : 'Your Gym';

        switch ($sectionKey) {
            case 'hero':
                return [
                    'slides' => [
                        [
                            'id' => 'h1',
                            'title' => 'Transform Your Body. Elevate Your Mind.',
                            'subtitle' => 'High-Speed Optical Access Gates • Encrypted Barcode Passes • Olympic Free Weights',
                            'tag' => 'Next-Gen Fitness',
                            'image' => '/images/hero/1.webp',
                            'cta_text' => 'Get Your Digital Pass',
                            'cta_action' => 'pass',
                            'secondary_cta' => 'Explore Plans',
                            'secondary_action' => 'plans',
                        ],
                        [
                            'id' => 'h2',
                            'title' => 'Contactless Biometric Gate Entry.',
                            'subtitle' => 'Zero Plastic Keycards • Instant Optical Access Gate Tap • Real-Time Attendance Logs',
                            'tag' => 'Smart Pass Technology',
                            'image' => '/images/hero/2.webp',
                            'cta_text' => 'View Gate Barcode',
                            'cta_action' => 'pass',
                            'secondary_cta' => 'Member Sign In',
                            'secondary_action' => 'login',
                        ],
                        [
                            'id' => 'h3',
                            'title' => 'Master Coaches & Strength Science.',
                            'subtitle' => 'Personalized Biomechanics • Hypertrophy Rigs • Combat Boxing Arena',
                            'tag' => 'Elite Training',
                            'image' => '/images/hero/3.webp',
                            'cta_text' => 'View Class Timetable',
                            'cta_action' => 'schedule',
                            'secondary_cta' => 'Book Trainer',
                            'secondary_action' => 'coaches',
                        ],
                    ],
                ];

            case 'amenities':
                return [
                    'items' => [
                        [
                            'id' => 'am1',
                            'title' => 'Olympic Strength & Free Weights',
                            'category' => 'Strength',
                            'description' => 'Eleiko competition barbells, calibrated bumper plates, calibrated dumbbells up to 60kg, and heavy-duty power racks.',
                            'image' => '/images/amenities/l1.webp',
                            'features' => ['6 Competition Racks', 'Calibrated Plates', 'Deadlift Platforms'],
                        ],
                        [
                            'id' => 'am2',
                            'title' => 'Cardio Performance & MetCon',
                            'category' => 'Cardio',
                            'description' => 'Woodway curved treadmills, Concept2 Rowers, SkiErgs, and Assault Bikes equipped with telemetry monitors.',
                            'image' => '/images/amenities/l2.webp',
                            'features' => ['Concept2 Ergs', 'Curved Speedmills', 'Heart-Rate Sync'],
                        ],
                        [
                            'id' => 'am3',
                            'title' => 'Combat Boxing & Striking Ring',
                            'category' => 'Combat',
                            'description' => 'Full-size regulation boxing ring, heavy teardrop bags, speedballs, and sparring pads for MMA and kickboxing.',
                            'image' => '/images/amenities/l4.webp',
                            'features' => ['Regulation Ring', 'Teardrop Heavy Bags', 'Padwork Drills'],
                        ],
                        [
                            'id' => 'am4',
                            'title' => 'Cold Plunge & Contrast Recovery',
                            'category' => 'Recovery',
                            'description' => 'Sub-3°C chilled ice tubs and infrared dry sauna to accelerate muscular regeneration and reduce systemic fatigue.',
                            'image' => '/images/amenities/l3.webp',
                            'features' => ['3°C Chilled Plunge', 'Infrared Sauna', 'Towel Service'],
                        ],
                    ],
                ];

            case 'classes':
                return [
                    'Monday' => [
                        [
                            'id' => 'm1',
                            'name' => 'Olympic Strength & Barbell',
                            'category' => 'Strength',
                            'time' => '06:30 AM - 07:30 AM',
                            'duration' => '60 min',
                            'trainer' => 'Marcus Vance',
                            'intensity' => 'High',
                            'capacity' => '14 / 16 Booked',
                            'room' => 'Main Rig & Racks',
                            'image' => '/images/classes/l1.webp',
                        ],
                        [
                            'id' => 'm2',
                            'name' => 'High-Octane HIIT Blast',
                            'category' => 'Cardio',
                            'time' => '09:00 AM - 10:00 AM',
                            'duration' => '60 min',
                            'trainer' => 'Sarah Jenkins',
                            'intensity' => 'High',
                            'capacity' => '18 / 20 Booked',
                            'room' => 'Functional Turf Zone',
                            'image' => '/images/classes/l2.webp',
                        ],
                        [
                            'id' => 'm3',
                            'name' => 'Power Vinyasa & Core',
                            'category' => 'Yoga',
                            'time' => '12:00 PM - 01:00 PM',
                            'duration' => '60 min',
                            'trainer' => 'Elena Rostova',
                            'intensity' => 'Moderate',
                            'capacity' => '10 / 15 Booked',
                            'room' => 'Mind & Body Studio',
                            'image' => '/images/classes/l3.webp',
                        ],
                        [
                            'id' => 'm4',
                            'name' => 'Muay Thai & Heavy Bag Drills',
                            'category' => 'Combat',
                            'time' => '06:00 PM - 07:15 PM',
                            'duration' => '75 min',
                            'trainer' => 'David Chen',
                            'intensity' => 'High',
                            'capacity' => '12 / 12 Full',
                            'room' => 'Combat Ring & Bags',
                            'image' => '/images/classes/l4.webp',
                        ],
                    ],
                    'Tuesday' => [
                        [
                            'id' => 't1',
                            'name' => 'Functional Athletic Conditioning',
                            'category' => 'Strength',
                            'time' => '07:00 AM - 08:00 AM',
                            'duration' => '60 min',
                            'trainer' => 'Marcus Vance',
                            'intensity' => 'High',
                            'capacity' => '15 / 16 Booked',
                            'room' => 'Turf & Power Sleds',
                            'image' => '/images/classes/l5.webp',
                        ],
                        [
                            'id' => 't2',
                            'name' => 'Spin & Endurance Sprint',
                            'category' => 'Cardio',
                            'time' => '10:00 AM - 11:00 AM',
                            'duration' => '60 min',
                            'trainer' => 'Sarah Jenkins',
                            'intensity' => 'High',
                            'capacity' => '20 / 22 Booked',
                            'room' => 'Cardio Studio A',
                            'image' => '/images/classes/l6.webp',
                        ],
                        [
                            'id' => 't3',
                            'name' => 'Boxing Fundamentals & Footwork',
                            'category' => 'Combat',
                            'time' => '05:30 PM - 06:45 PM',
                            'duration' => '75 min',
                            'trainer' => 'David Chen',
                            'intensity' => 'High',
                            'capacity' => '8 / 14 Booked',
                            'room' => 'Combat Ring & Bags',
                            'image' => '/images/classes/l4.webp',
                        ],
                        [
                            'id' => 't4',
                            'name' => 'Deep Fascial Mobility & Recovery',
                            'category' => 'Yoga',
                            'time' => '07:30 PM - 08:30 PM',
                            'duration' => '60 min',
                            'trainer' => 'Elena Rostova',
                            'intensity' => 'Low',
                            'capacity' => '9 / 18 Booked',
                            'room' => 'Mind & Body Studio',
                            'image' => '/images/classes/l3.webp',
                        ],
                    ],
                    'Wednesday' => [
                        [
                            'id' => 'w1',
                            'name' => 'Deadlift & Posterior Chain Lab',
                            'category' => 'Strength',
                            'time' => '06:30 AM - 07:45 AM',
                            'duration' => '75 min',
                            'trainer' => 'Marcus Vance',
                            'intensity' => 'High',
                            'capacity' => '12 / 12 Full',
                            'room' => 'Olympic Platforms',
                            'image' => '/images/classes/l1.webp',
                        ],
                        [
                            'id' => 'w2',
                            'name' => 'MetCon Calorie Furnace',
                            'category' => 'Cardio',
                            'time' => '09:00 AM - 10:00 AM',
                            'duration' => '60 min',
                            'trainer' => 'Sarah Jenkins',
                            'intensity' => 'High',
                            'capacity' => '16 / 20 Booked',
                            'room' => 'Functional Turf Zone',
                            'image' => '/images/classes/l2.webp',
                        ],
                        [
                            'id' => 'w3',
                            'name' => 'Ashtanga Yoga Flow',
                            'category' => 'Yoga',
                            'time' => '05:00 PM - 06:00 PM',
                            'duration' => '60 min',
                            'trainer' => 'Elena Rostova',
                            'intensity' => 'Moderate',
                            'capacity' => '14 / 15 Booked',
                            'room' => 'Mind & Body Studio',
                            'image' => '/images/classes/l3.webp',
                        ],
                        [
                            'id' => 'w4',
                            'name' => 'Kickboxing Padwork & Sparring',
                            'category' => 'Combat',
                            'time' => '06:30 PM - 07:45 PM',
                            'duration' => '75 min',
                            'trainer' => 'David Chen',
                            'intensity' => 'High',
                            'capacity' => '11 / 14 Booked',
                            'room' => 'Combat Ring & Bags',
                            'image' => '/images/classes/l4.webp',
                        ],
                    ],
                    'Thursday' => [
                        [
                            'id' => 'th1',
                            'name' => 'Hypertrophy Upper Body Blitz',
                            'category' => 'Strength',
                            'time' => '07:00 AM - 08:00 AM',
                            'duration' => '60 min',
                            'trainer' => 'Marcus Vance',
                            'intensity' => 'High',
                            'capacity' => '14 / 16 Booked',
                            'room' => 'Main Free Weights',
                            'image' => '/images/classes/l5.webp',
                        ],
                        [
                            'id' => 'th2',
                            'name' => 'Row & Ski-Erg Interval Matrix',
                            'category' => 'Cardio',
                            'time' => '11:00 AM - 12:00 PM',
                            'duration' => '60 min',
                            'trainer' => 'Sarah Jenkins',
                            'intensity' => 'High',
                            'capacity' => '10 / 12 Booked',
                            'room' => 'Cardio Theater',
                            'image' => '/images/classes/l6.webp',
                        ],
                        [
                            'id' => 'th3',
                            'name' => 'Combat Conditioning & Clinch',
                            'category' => 'Combat',
                            'time' => '05:30 PM - 06:45 PM',
                            'duration' => '75 min',
                            'trainer' => 'David Chen',
                            'intensity' => 'High',
                            'capacity' => '13 / 14 Booked',
                            'room' => 'Combat Ring & Bags',
                            'image' => '/images/classes/l4.webp',
                        ],
                        [
                            'id' => 'th4',
                            'name' => 'Restorative Yin Yoga & Sound Bath',
                            'category' => 'Yoga',
                            'time' => '07:15 PM - 08:15 PM',
                            'duration' => '60 min',
                            'trainer' => 'Elena Rostova',
                            'intensity' => 'Low',
                            'capacity' => '17 / 18 Booked',
                            'room' => 'Mind & Body Studio',
                            'image' => '/images/classes/l3.webp',
                        ],
                    ],
                    'Friday' => [
                        [
                            'id' => 'f1',
                            'name' => 'Full Body Barbell Complex',
                            'category' => 'Strength',
                            'time' => '06:30 AM - 07:30 AM',
                            'duration' => '60 min',
                            'trainer' => 'Marcus Vance',
                            'intensity' => 'High',
                            'capacity' => '16 / 16 Full',
                            'room' => 'Olympic Platforms',
                            'image' => '/images/classes/l1.webp',
                        ],
                        [
                            'id' => 'f2',
                            'name' => 'Friday Sunset HIIT & Burn',
                            'category' => 'Cardio',
                            'time' => '05:00 PM - 06:00 PM',
                            'duration' => '60 min',
                            'trainer' => 'Sarah Jenkins',
                            'intensity' => 'High',
                            'capacity' => '19 / 20 Booked',
                            'room' => 'Functional Turf Zone',
                            'image' => '/images/classes/l2.webp',
                        ],
                        [
                            'id' => 'f3',
                            'name' => 'Friday Night Fight Club Boxing',
                            'category' => 'Combat',
                            'time' => '06:30 PM - 08:00 PM',
                            'duration' => '90 min',
                            'trainer' => 'David Chen',
                            'intensity' => 'High',
                            'capacity' => '14 / 14 Full',
                            'room' => 'Combat Ring & Bags',
                            'image' => '/images/classes/l4.webp',
                        ],
                    ],
                    'Saturday' => [
                        [
                            'id' => 'sa1',
                            'name' => 'Weekend Warrior Strongman Session',
                            'category' => 'Strength',
                            'time' => '08:30 AM - 10:00 AM',
                            'duration' => '90 min',
                            'trainer' => 'Marcus Vance',
                            'intensity' => 'High',
                            'capacity' => '18 / 20 Booked',
                            'room' => 'Turf & Free Weight Rig',
                            'image' => '/images/classes/l5.webp',
                        ],
                        [
                            'id' => 'sa2',
                            'name' => 'Cardio Endurance Camp',
                            'category' => 'Cardio',
                            'time' => '10:30 AM - 11:45 AM',
                            'duration' => '75 min',
                            'trainer' => 'Sarah Jenkins',
                            'intensity' => 'High',
                            'capacity' => '15 / 20 Booked',
                            'room' => 'Functional Turf Zone',
                            'image' => '/images/classes/l6.webp',
                        ],
                        [
                            'id' => 'sa3',
                            'name' => 'Weekend Recovery Yoga & Alignment',
                            'category' => 'Yoga',
                            'time' => '12:15 PM - 01:30 PM',
                            'duration' => '75 min',
                            'trainer' => 'Elena Rostova',
                            'intensity' => 'Low',
                            'capacity' => '12 / 18 Booked',
                            'room' => 'Mind & Body Studio',
                            'image' => '/images/classes/l3.webp',
                        ],
                    ],
                    'Sunday' => [
                        [
                            'id' => 'su1',
                            'name' => 'Active Recovery & Guided Mobility',
                            'category' => 'Yoga',
                            'time' => '09:00 AM - 10:15 AM',
                            'duration' => '75 min',
                            'trainer' => 'Elena Rostova',
                            'intensity' => 'Low',
                            'capacity' => '14 / 18 Booked',
                            'room' => 'Mind & Body Studio',
                            'image' => '/images/classes/l3.webp',
                        ],
                        [
                            'id' => 'su2',
                            'name' => 'Open Gym Free Weight Technique',
                            'category' => 'Strength',
                            'time' => '11:00 AM - 01:00 PM',
                            'duration' => '120 min',
                            'trainer' => 'Marcus Vance',
                            'intensity' => 'Moderate',
                            'capacity' => 'Open Floor Access',
                            'room' => 'Olympic Free Weights',
                            'image' => '/images/classes/l1.webp',
                        ],
                        [
                            'id' => 'su3',
                            'name' => 'Muay Thai Sparring Lab (Advanced)',
                            'category' => 'Combat',
                            'time' => '03:00 PM - 04:30 PM',
                            'duration' => '90 min',
                            'trainer' => 'David Chen',
                            'intensity' => 'High',
                            'capacity' => '8 / 10 Booked',
                            'room' => 'Combat Ring & Bags',
                            'image' => '/images/classes/l4.webp',
                        ],
                    ],
                ];

            case 'trainers':
                return [
                    'items' => [
                        [
                            'id' => 't1',
                            'name' => 'Marcus Vance',
                            'title' => 'Head Strength & Conditioning',
                            'image' => '/images/team/1.webp',
                            'certifications' => ['CSCS Certified', 'USAW Level 2', 'EXOS Specialist'],
                            'experience' => '11+ Years Experience',
                            'transformations' => '420+ Member Transformations',
                            'specialties' => ['Hypertrophy', 'Olympic Barbell', 'Powerlifting PRs'],
                            'quote' => 'Discipline and mechanical efficiency beat random intensity every single time.',
                        ],
                        [
                            'id' => 't2',
                            'name' => 'Elena Rostova',
                            'title' => 'Olympic Lifting & Biomechanics',
                            'image' => '/images/team/2.webp',
                            'certifications' => ['NASM-CES', 'FMS Level 2', 'B.Sc Exercise Science'],
                            'experience' => '8+ Years Experience',
                            'transformations' => '310+ Member Transformations',
                            'specialties' => ['Spine & Hip Mobility', 'Postural Restoration', 'Kettlebell Lab'],
                            'quote' => 'True power comes from joints that move freely without pain or restriction.',
                        ],
                        [
                            'id' => 't3',
                            'name' => 'David Chen',
                            'title' => 'Head Combat & Sparring Coach',
                            'image' => '/images/team/3.webp',
                            'certifications' => ['Golden Gloves Boxing', 'Muay Thai Kru', 'ISSA Elite'],
                            'experience' => '12+ Years Experience',
                            'transformations' => '260+ Ring & Fighter Clients',
                            'specialties' => ['Striking Power', 'Footwork Mechanics', 'Metabolic Endurance'],
                            'quote' => 'You do not rise to the occasion; you sink to the level of your daily training.',
                        ],
                        [
                            'id' => 't4',
                            'name' => 'Sarah Jenkins',
                            'title' => 'Functional Conditioning & Nutrition',
                            'image' => '/images/team/4.webp',
                            'certifications' => ['Precision Nutrition L2', 'ACE-CPT', 'CrossFit L2'],
                            'experience' => '7+ Years Experience',
                            'transformations' => '390+ Member Transformations',
                            'specialties' => ['Fat Loss Protocols', 'MetCon Circuitry', 'Competition Prep'],
                            'quote' => 'Fuel your body like the elite machine you expect it to perform as.',
                        ],
                    ],
                ];

            case 'testimonials':
                return [
                    'items' => [
                        [
                            'id' => '1',
                            'name' => 'Alexander Cole',
                            'role' => 'VIP Annual Member',
                            'avatar' => '/images/testimonial/1.webp',
                            'rating' => 5,
                            'achievement' => '🔥 Lost 14kg & Built 5kg Muscle',
                            'comment' => 'The speed of the access gates is insane. Having my pass right on my phone with zero plastic cards or delays makes training at 6 AM completely frictionless.',
                            'duration' => 'Member for 14 Months',
                        ],
                        [
                            'id' => '2',
                            'name' => 'Sophia Laurent',
                            'role' => 'Strength & Conditioning Member',
                            'avatar' => '/images/testimonial/2.webp',
                            'rating' => 5,
                            'achievement' => '🏋️ Deadlift PR: 70kg → 145kg',
                            'comment' => 'I was intimidated by free weights until Coach Marcus walked me through barbell mechanics. The atmosphere is supportive and motivating. Best gym in town.',
                            'duration' => 'Member for 9 Months',
                        ],
                        [
                            'id' => '3',
                            'name' => 'Michael Henderson',
                            'role' => 'Combat & HIIT Enthusiast',
                            'avatar' => '/images/testimonial/3.webp',
                            'rating' => 5,
                            'achievement' => '⚡ 180 Workouts Logged Streak',
                            'comment' => 'From the boxing ring to the ice recovery plunge, every corner of this facility is world class. The live portal attendance stats keep me accountable.',
                            'duration' => 'Member for 2 Years',
                        ],
                        [
                            'id' => '4',
                            'name' => 'Amara Obi',
                            'role' => 'Functional & Yoga Member',
                            'avatar' => '/images/testimonial/4.webp',
                            'rating' => 5,
                            'achievement' => '🧘 Cured Lower Back Stiffness',
                            'comment' => 'The combination of intense morning HIIT followed by Elena’s recovery yoga completely healed my posture. Renewing online takes 5 seconds flat.',
                            'duration' => 'Member for 7 Months',
                        ],
                    ],
                ];

            case 'faqs':
                return [
                    'items' => [
                        [
                            'q' => 'Do I need a physical keycard or RFID fob?',
                            'a' => 'No! Your digital entrance pass with an encrypted high-contrast QR barcode is saved directly on your phone. You can also register your biometric fingerprint at the front desk for instant contactless entry.',
                        ],
                        [
                            'q' => 'How do I renew or upgrade my membership plan?',
                            'a' => 'Simply click "Renew / Upgrade Online" on your dashboard or select any plan on this page. Payments are verified instantly (card, bank transfer, USSD) and your access gate access extends immediately.',
                        ],
                        [
                            'q' => 'Can I pause or freeze my membership if I travel?',
                            'a' => 'Yes, Quarterly and Annual members can pause their subscription for up to 30 days per year. Just notify our front desk team or contact support.',
                        ],
                        [
                            'q' => 'Are boxing and golf simulator facilities included in standard memberships?',
                            'a' => 'Standard memberships cover unlimited gym floor, strength, and cardio access. Boxing group classes, 1-on-1 sparring, and our TrackMan golf simulator suite can be added directly under the Extras & Amenities tab.',
                        ],
                    ],
                ];

            case 'contact_socials':
                return [
                    'phone' => '+234 812 345 6789',
                    'email' => 'frontdesk@example.com',
                    'address' => '14 Admiralty Way, Lekki Phase 1, Lagos, Nigeria',
                    'hours_weekday' => '06:00 – 22:00',
                    'hours_weekend' => '07:00 – 21:00',
                    'hours_holidays' => '08:00 – 18:00',
                    'social_fb' => 'https://facebook.com',
                    'social_ig' => 'https://instagram.com',
                    'social_x' => 'https://x.com',
                    'social_yt' => 'https://youtube.com',
                    'social_tk' => 'https://tiktok.com',
                    'social_wa' => 'https://wa.me/?text=Hello%20Gym%20Front%20Desk,%20I%20have%20an%20inquiry%20regarding%20memberships.',
                ];

            case 'section_visibility':
                return [
                    'hero' => true,
                    'pulse' => true,
                    'how_it_works' => true,
                    'amenities' => true,
                    'classes' => true,
                    'bmi' => true,
                    'trainers' => true,
                    'plans' => true,
                    'testimonials' => true,
                    'faqs' => true,
                ];

            default:
                return [];
        }
    }
}
