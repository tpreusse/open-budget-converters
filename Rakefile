# encoding: UTF-8

require 'csv'
require 'json'
require 'active_support/all'

require './lib/models/open_budget.rb'


namespace :generate_json do
  desc "creates json file"
  task :from_csv do
    puts "start processing"
    budget = OpenBudget::Budget.new

    budget.load_meta 'data/meta.json'
    budget.from_zurich_csv 'source/B13_Institution_Konzernkonto.csv'

    File.open("data/data.json", 'wb') do |file|
      file.write budget.to_json
    end
    puts "done"
  end
end
