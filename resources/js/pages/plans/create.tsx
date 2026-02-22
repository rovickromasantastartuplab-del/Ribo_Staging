import React from 'react';
import PlanForm from './form';

interface AvailableCurrency {
  id: number;
  code: string;
  symbol: string;
  name: string;
}

interface Props {
  hasDefaultPlan: boolean;
  availableCurrencies?: AvailableCurrency[];
}

export default function CreatePlan({ hasDefaultPlan, availableCurrencies = [] }: Props) {
  return <PlanForm hasDefaultPlan={hasDefaultPlan} availableCurrencies={availableCurrencies} currencyPrices={[]} />;
}