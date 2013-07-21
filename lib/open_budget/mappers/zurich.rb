# encoding: UTF-8

module OpenBudget
  module Mappers
    module Zurich

      def from_zurich_csv file_path
        # :encoding => 'windows-1251:utf-8',
        CSV.foreach(file_path, :col_sep => ';', :headers => true, :header_converters => :symbol, :converters => :all) do |row|

          # p row

          row[:bezeichnung].strip!
          code = row[:code].to_s
          konto = row[:konto].to_s.strip.gsub(' ', '_')
          account_type = konto[0].to_i
          # if [3, 4, 5, 6].include? account_type

          dienstabteilung = row[:dienstabteilung]
          if dienstabteilung == 'Kultur' && /^15[0-9]{2}$/.match(code)
            code = '1501'
          elsif dienstabteilung == 'Museum Rietberg' && /^152[0-4]$/.match(code)
            code = '1520'
          end

          id_path = [
            code[0..1], # Departement
            code, # [2..3] = Dienstabteilung
            # konto[0..1], # seperate by account purpose
            konto[0..-1] # account # minus account_type
          ].each(&:strip).reject(&:blank?)
          names = [
            row[:departement],
            dienstabteilung,
            # '-',
            row[:bezeichnung]
          ]

          node = get_or_create_node id_path, names

          # ToDo: seperate "investitionsrechnung" [5, 6] and "laufende rechnung" [3, 4]


          method = nil
          case row[:bezeichnung]
          # overview row
          when 'Aufwand'#, 'Ausgaben'
            method = :add_gross_cost
          when 'Ertrag'#, 'Einnahmen'
            method = :add_revenue
          # detail row
          else
            if konto.present?
              case account_type
              when 3#, 5 # Aufwand, Ausgaben
                method = :add_gross_cost
              when 4#, 6 # Ertrag, Einnahmen
                method = :add_revenue
              end
            end
          end

          values = [row[:budget_2012_fr], row[:budget_2013_fr], row[:rechnung_2011_fr]].collect do |value|
            value.to_s.gsub('’','').to_f
          end
          case method
          when :add_revenue
            values.collect! do |value|
              value * -1
            end
          end
          if method
            node.method(method).call('budgets', 2012, values[0])
            node.method(method).call('budgets', 2013, values[1])
            # node.method(method).call('accounts', 2011, values[2])
          end

          # budget_2013_fr bezeichnung_sofern_gemss_art_4_fvo_erforderlich:nil

          # if [3, 5].include? account_type
          #   node.add_gross_cost('accounts', row[:jahr], row[:saldo])
          # elsif [4, 6].include? account_type
          #   node.add_revenue('accounts', row[:jahr], row[:saldo])
          # end
          # end
        end
      end

    end
  end
end