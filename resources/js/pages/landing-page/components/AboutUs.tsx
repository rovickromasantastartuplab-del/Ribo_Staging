import React from 'react';
import { Target, Heart, Award, Lightbulb, Star, Shield, Users, Zap } from 'lucide-react';
import { useScrollAnimation } from '../../../hooks/useScrollAnimation';
import { useTranslation } from 'react-i18next';

interface AboutUsProps {
  brandColor?: string;
  settings: any;
  sectionData: {
    title?: string;
    description?: string;
    story_title?: string;
    story_content?: string;
    stats?: Array<{
      value: string;
      label: string;
      color: string;
    }>;
    values?: Array<{
      title: string;
      description: string;
      icon: string;
    }>;
    image_title?: string;
    image_subtitle?: string;
    image_icon?: string;
  };
}

// Icon mapping for dynamic icons
const iconMap: Record<string, React.ComponentType<any>> = {
  'target': Target,
  'heart': Heart,
  'award': Award,
  'lightbulb': Lightbulb,
  'star': Star,
  'shield': Shield,
  'users': Users,
  'zap': Zap
};

export default function AboutUs({ settings, sectionData, brandColor = '#3b82f6' }: AboutUsProps) {
  const { ref, isVisible } = useScrollAnimation();
  const { t } = useTranslation();

  // Helper to get full URL for images
  const getImageUrl = (path: string) => {
    if (!path) return null;
    if (path.startsWith('http')) return path;
    return `${window.appSettings.imageUrl}${path}`;
  };

  const sectionImage = getImageUrl(sectionData.image);
  const backgroundColor = sectionData.background_color || '#f9fafb';
  // Default data if none provided
  const defaultValues = [
    {
      icon: 'target',
      title: t('Our Mission'),
      description: t('To revolutionize sales management by providing businesses with an all-in-one SaaS platform that simplifies, automates, and accelerates growth.')
    },
    {
      icon: 'heart',
      title: t('Our Values'),
      description: t('We believe in innovation, transparency, and empowering teams with tools that drive real business success.')
    },
    {
      icon: 'award',
      title: t('Our Commitment'),
      description: t('Delivering exceptional user experience with robust technology, secure infrastructure, and world-class support.')
    },
    {
      icon: 'lightbulb',
      title: t('Our Vision'),
      description: t('A future where every business can manage leads, deals, and customers seamlessly — unlocking their full sales potential.')
    }
  ];

  const defaultStats = [
    { value: '4+ Years', label: t('Industry Experience'), color: 'blue' },
    { value: '10K+', label: t('Happy Users'), color: 'green' },
    { value: '5K+', label: t('Businesses Powered'), color: 'purple' }
  ];

  const values = sectionData.values && sectionData.values.length > 0
    ? sectionData.values
    : defaultValues;

  const stats = sectionData.stats && sectionData.stats.length > 0
    ? sectionData.stats
    : defaultStats;

  return (
    <section id="about" className="py-12 sm:py-16 lg:py-20" style={{ backgroundColor }} ref={ref}>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className={`text-center mb-8 sm:mb-12 lg:mb-16 transition-all duration-700 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'}`}>
          <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
            {sectionData.title || t('About Our Sales SaaS')}
          </h2>
          <p className="text-lg text-gray-600 max-w-3xl mx-auto leading-relaxed font-medium">
            {sectionData.description || t('We are dedicated to simplifying and automating the entire sales lifecycle for businesses of all sizes.')}
          </p>
        </div>

        <div className="grid lg:grid-cols-2 gap-8 sm:gap-12 lg:gap-16 items-center mb-8 sm:mb-12 lg:mb-16">
          {/* Left Content */}
          <div>
            <h3 className="text-2xl font-bold text-gray-900 mb-6">
              {sectionData.story_title || t('Empowering Sales Teams Since 2020')}
            </h3>
            <div className="text-gray-600 mb-8 leading-relaxed" dangerouslySetInnerHTML={{
              __html: (sectionData.story_content || t('Founded by a group of sales professionals and technology experts, our Sales SaaS was created to solve the common challenges businesses face in managing leads, orders, invoices, and projects. Today, we power thousands of businesses worldwide with a reliable, scalable, and easy-to-use platform.')).replace(/\n/g, '</p><p className="mb-6">')
            }} />

            {stats.length > 0 && (
              <div className="flex items-center gap-8">
                {stats.map((stat, index) => (
                  <div key={index} className="text-center">
                    <div className="text-2xl font-bold text-gray-900">{stat.value}</div>
                    <div className="text-sm text-gray-600">{stat.label}</div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Right Content - Image or Visual */}
          <div className="bg-white rounded-xl p-8 border border-gray-200 h-96 flex items-center justify-center">
            {sectionImage ? (
              <img src={sectionImage} alt={t('About Us')} className="max-w-full max-h-full object-contain rounded-lg" />
            ) : (
              <div className="text-center">
                <div className="w-24 h-24 bg-gray-100 rounded-full mx-auto mb-6 flex items-center justify-center">
                  <span className="text-3xl">{sectionData.image_icon || '🚀'}</span>
                </div>
                <h4 className="text-xl font-semibold text-gray-900 mb-2">
                  {sectionData.image_title || t('Innovation Driven')}
                </h4>
                <p className="text-gray-600">
                  {sectionData.image_subtitle || t('Building the future of networking')}
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Values Grid */}
        <div className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8 transition-all duration-700 delay-500 ${isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'}`}>
          {values.map((value, index) => {
            const IconComponent = iconMap[value.icon] || Target;
            return (
              <div key={index} className="text-center bg-white p-6 rounded-xl border border-gray-200">
                <div className="w-12 h-12 rounded-lg flex items-center justify-center mx-auto mb-4" style={{ backgroundColor: `${brandColor}15` }}>
                  <IconComponent className="w-6 h-6" style={{ color: brandColor }} />
                </div>
                <h3 className="text-lg font-semibold text-gray-900 mb-3">
                  {value.title}
                </h3>
                <p className="text-gray-600 text-sm leading-relaxed">
                  {value.description}
                </p>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
